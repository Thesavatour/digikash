<?php

namespace App\Services\Kyc\Drivers;

use App\Enums\KycStatus;
use App\Models\Admin;
use App\Models\KycSubmission;
use App\Models\User;
use App\Notifications\TemplateNotification;
use App\Services\Kyc\Contracts\KycLiveVerifier;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Didit hosted KYC verification (redirect / webhook).
 *
 * Session creation uses POST /v3/session/ with x-api-key.
 * Results arrive via signed webhooks (status.updated).
 */
class DiditKycLiveVerifier implements KycLiveVerifier
{
    public function isEnabled(): bool
    {
        return kyc_live_driver() === 'didit'
            && filled($this->apiKey())
            && filled($this->workflowId());
    }

    public function driver(): string
    {
        return 'didit';
    }

    public function startSession(User $user): array
    {
        // Session URL is created on demand when the user starts Didit
        // (avoids burning API credits on every KYC page view).
        return [
            'mode'             => 'redirect',
            'session_url'      => null,
            'session_id'       => null,
            'require_selfie'   => false,
            'require_document' => false,
            'provider'         => 'didit',
        ];
    }

    /**
     * Create a Didit verification session for the user.
     *
     * @return array{session_id: string, session_url: string, session_token?: string|null}
     */
    public function createSession(User $user, ?string $callbackUrl = null, ?int $submissionId = null): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException(__('Didit verification is not configured.'));
        }

        $payload = [
            'workflow_id' => $this->workflowId(),
            'vendor_data' => $submissionId
                ? 'kyc_submission:'.$submissionId
                : 'user:'.$user->id,
            'callback'    => $callbackUrl ?: route('user.settings.kyc.verify'),
            'metadata'    => [
                'user_id'       => $user->id,
                'email'         => $user->email,
                'submission_id' => $submissionId,
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders([
                    'x-api-key' => $this->apiKey(),
                ])
                ->post(rtrim($this->baseUrl(), '/').'/v3/session/', $payload)
                ->throw();
        } catch (RequestException $e) {
            Log::error('Didit session create failed', [
                'user_id' => $user->id,
                'status'  => $e->response?->status(),
                'body'    => $e->response?->json() ?? $e->response?->body(),
            ]);

            throw new RuntimeException(__('Could not start Didit verification. Check API key and workflow ID.'));
        }

        $data = $response->json() ?? [];
        $sessionId = (string) ($data['session_id'] ?? $data['id'] ?? '');
        $sessionUrl = (string) ($data['url'] ?? '');

        if ($sessionId === '' || $sessionUrl === '') {
            Log::error('Didit session create returned unexpected payload', ['body' => $data]);

            throw new RuntimeException(__('Didit did not return a verification session URL.'));
        }

        return [
            'session_id'    => $sessionId,
            'session_url'   => $sessionUrl,
            'session_token' => $data['session_token'] ?? null,
        ];
    }

    public function handleCallback(Request $request): array
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Didit webhook signature verification failed');

            return ['handled' => false, 'status' => 'invalid_signature'];
        }

        $webhookType = (string) $request->input('webhook_type', '');
        $status = (string) $request->input('status', '');
        $sessionId = (string) ($request->input('session_id') ?? '');
        $vendorData = (string) ($request->input('vendor_data') ?? '');

        if (! in_array($webhookType, ['status.updated', 'data.updated'], true)) {
            return ['handled' => true, 'status' => 'ignored', 'payload' => $request->all()];
        }

        $submission = $this->resolveSubmission($vendorData, $sessionId);

        if (! $submission) {
            Log::info('Didit webhook: no matching KYC submission', [
                'vendor_data' => $vendorData,
                'session_id'  => $sessionId,
                'status'      => $status,
            ]);

            return ['handled' => true, 'status' => 'no_submission', 'payload' => $request->all()];
        }

        $data = $submission->submission_data ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        $data['live_verification'] = array_merge(
            is_array($data['live_verification'] ?? null) ? $data['live_verification'] : [],
            [
                'driver'       => 'didit',
                'session_id'   => $sessionId !== '' ? $sessionId : ($data['live_verification']['session_id'] ?? null),
                'didit_status' => $status,
                'webhook_type' => $webhookType,
                'updated_at'   => now()->toIso8601String(),
                'decision'     => $request->input('decision'),
            ]
        );

        $mapped = $this->mapDiditStatus($status);

        $submission->submission_data = $data;
        if ($mapped !== null) {
            $submission->status = $mapped;
        }
        $submission->save();

        return [
            'handled' => true,
            'status'  => $mapped?->name ?? $status,
            'payload' => [
                'submission_id' => $submission->id,
                'didit_status'  => $status,
            ],
        ];
    }

    public function apiKey(): string
    {
        return (string) (setting('didit_api_key', config('kyc.didit.api_key')) ?: '');
    }

    public function workflowId(): string
    {
        return (string) (setting('didit_workflow_id', config('kyc.didit.workflow_id')) ?: '');
    }

    public function webhookSecret(): string
    {
        return (string) (setting('didit_webhook_secret', config('kyc.didit.webhook_secret')) ?: '');
    }

    public function baseUrl(): string
    {
        return (string) (setting('didit_base_url', config('kyc.didit.base_url', 'https://verification.didit.me')) ?: 'https://verification.didit.me');
    }

    private function mapDiditStatus(string $status): ?KycStatus
    {
        return match (strtolower(trim($status))) {
            'approved' => KycStatus::APPROVED,
            'declined', 'rejected', 'abandoned' => KycStatus::REJECTED,
            'in review', 'in_review', 'resubmitted', 'not started', 'in progress' => KycStatus::PENDING,
            default => null,
        };
    }

    private function resolveSubmission(string $vendorData, string $sessionId): ?KycSubmission
    {
        if (str_starts_with($vendorData, 'kyc_submission:')) {
            $id = (int) substr($vendorData, strlen('kyc_submission:'));

            return KycSubmission::query()->find($id);
        }

        if (str_starts_with($vendorData, 'user:')) {
            $userId = (int) substr($vendorData, strlen('user:'));

            return KycSubmission::query()
                ->where('user_id', $userId)
                ->latest('id')
                ->first();
        }

        if ($sessionId !== '') {
            return KycSubmission::query()
                ->where('submission_data->live_verification->session_id', $sessionId)
                ->latest('id')
                ->first();
        }

        if (ctype_digit($vendorData)) {
            return KycSubmission::query()
                ->where('user_id', (int) $vendorData)
                ->latest('id')
                ->first();
        }

        return null;
    }

    private function verifySignature(Request $request): bool
    {
        $secret = $this->webhookSecret();

        // Allow local/dev without a secret so the toggle can be tested before
        // a webhook destination is configured. Production should always set one.
        if ($secret === '') {
            return (bool) config('app.debug') || app()->environment('local', 'testing');
        }

        $timestamp = (string) ($request->header('X-Timestamp') ?? $request->input('timestamp') ?? '');
        if ($timestamp !== '' && abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signatureV2 = (string) ($request->header('X-Signature-V2') ?? '');
        if ($signatureV2 !== '' && $this->verifySignatureV2($request->all(), $signatureV2, $secret)) {
            return true;
        }

        $signatureSimple = (string) ($request->header('X-Signature-Simple') ?? '');
        if ($signatureSimple !== '' && $this->verifySignatureSimple($request->all(), $signatureSimple, $secret)) {
            return true;
        }

        $rawSignature = (string) ($request->header('X-Signature') ?? '');
        $rawBody = $request->getContent();
        if ($rawSignature !== '' && is_string($rawBody) && $rawBody !== '') {
            $expected = hash_hmac('sha256', $rawBody, $secret);

            return hash_equals($expected, $rawSignature);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function verifySignatureV2(array $body, string $signatureHeader, string $secret): bool
    {
        try {
            $canonical = $this->canonicalJson($this->shortenFloats($body));
            $expected = hash_hmac('sha256', $canonical, $secret);

            return hash_equals($expected, $signatureHeader);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function verifySignatureSimple(array $body, string $signatureHeader, string $secret): bool
    {
        $canonical = implode(':', [
            (string) ($body['timestamp'] ?? ''),
            (string) ($body['session_id'] ?? ''),
            (string) ($body['status'] ?? ''),
            (string) ($body['webhook_type'] ?? ''),
        ]);
        $expected = hash_hmac('sha256', $canonical, $secret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param  mixed  $data
     * @return mixed
     */
    private function shortenFloats(mixed $data): mixed
    {
        if (is_array($data)) {
            $out = [];
            foreach ($data as $key => $value) {
                $out[$key] = $this->shortenFloats($value);
            }

            return $out;
        }

        if (is_float($data) && floor($data) == $data) {
            return (int) $data;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function canonicalJson(array $data): string
    {
        $this->ksortRecursive($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ksortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }
}
