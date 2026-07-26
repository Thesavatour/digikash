<?php

namespace App\Services;

use App\Exceptions\NotifyErrorException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectUpdateServerClient
{
    /**
     * @return array<string, mixed>
     */
    public function activate(string $purchaseCode, ?string $domain = null): array
    {
        if ($this->isDemoMode()) {
            // Demo deployments never reach the real Envato server — only the
            // remote verification is skipped. We synthesise a realistic,
            // purchase-code-derived activation payload here and let the normal
            // service flow persist it exactly like a live activation.
            return $this->demoActivation($purchaseCode, $domain ?: request()->getHost());
        }

        return $this->post('licenses/activate', [
            'purchase_code' => $purchaseCode,
            'domain'        => $domain ?: request()->getHost(),
            'product_slug'  => config('project_updater.product_slug'),
            'item_id'       => config('project_updater.item_id'),
            'version'       => config('app.version'),
            'channel'       => config('project_updater.channel'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function check(?string $licenseToken): array
    {
        if ($this->isDemoMode()) {
            // Skip the remote update check in demo; report "up to date".
            return $this->demoUpdate();
        }

        return $this->post('updates/check', [
            'license_token' => $licenseToken,
            'domain'        => request()->getHost(),
            'product_slug'  => config('project_updater.product_slug'),
            'item_id'       => config('project_updater.item_id'),
            'version'       => config('app.version'),
            'channel'       => config('project_updater.channel'),
        ]);
    }

    /**
     * In demo mode the licensing server is never contacted — only the remote
     * VERIFICATION step is skipped. Activation, refresh, and update checks all
     * run through the normal persistence flow using locally generated,
     * purchase-code-derived data.
     */
    public function isDemoMode(): bool
    {
        return (bool) config('app.demo', false);
    }

    /**
     * Build a deterministic, purchase-code-derived activation payload so the
     * same code always yields the same buyer and support window. Shaped exactly
     * like a real server response so the service maps it without special-casing.
     *
     * @return array{success: bool, license: array<string, mixed>}
     */
    private function demoActivation(string $purchaseCode, string $domain): array
    {
        $seed          = crc32($purchaseCode);
        $compact       = preg_replace('/[^a-z0-9]/i', '', $purchaseCode) ?: 'demo';
        $buyer         = 'buyer_'.mb_strtolower(mb_substr($compact, 0, 8));
        $supportMonths = 6 + ($seed % 7); // 6–12 months, varies per purchase code

        return [
            'success' => true,
            'license' => [
                'token'           => hash('sha256', $purchaseCode.'|'.$domain),
                'buyer_username'  => $buyer,
                'item_id'         => (string) config('project_updater.item_id'),
                'status'          => 'active',
                'supported_until' => now()->addMonths($supportMonths)->toISOString(),
                'demo'            => true,
            ],
        ];
    }

    /**
     * Build a demo "up to date" update payload (matching the installed version)
     * so the update check reports the latest release without a server call.
     *
     * @return array{success: bool, update: array<string, mixed>}
     */
    private function demoUpdate(): array
    {
        return [
            'success' => true,
            'update'  => [
                'version'      => (string) config('app.version'),
                'channel'      => (string) config('project_updater.channel'),
                'changelog'    => [],
                'requirements' => [],
                'release_date' => now()->toISOString(),
                'demo'         => true,
            ],
        ];
    }

    /**
     * Verify a stored license against the update server WITHOUT throwing.
     *
     * The outcome deliberately distinguishes a confirmed-invalid licence
     * (HTTP 401/403 from the real server — a piracy signal) from a transient
     * problem (timeout, 5xx, rate limit, DNS) so the runtime guard can stay
     * soft and never lock a genuine buyer out during an outage.
     *
     * @return array{outcome: string, status: int, message: string|null}
     */
    public function verifyLicense(string $licenseToken, string $domain): array
    {
        if ($this->isDemoMode()) {
            // No remote license validation in demo mode — always considered valid.
            return ['outcome' => 'valid', 'status' => 200, 'message' => null];
        }

        $serverUrl = config('project_updater.server_url');

        if (blank($serverUrl) || blank($licenseToken) || blank($domain)) {
            return ['outcome' => 'unreachable', 'status' => 0, 'message' => 'License token, domain, or server URL is missing.'];
        }

        $payload = [
            'license_token' => $licenseToken,
            'domain'        => $domain,
            'product_slug'  => config('project_updater.product_slug'),
            'item_id'       => config('project_updater.item_id'),
            'version'       => config('app.version'),
            'channel'       => config('project_updater.channel'),
        ];

        try {
            $response = Http::timeout((int) config('project_updater.timeout', 20))
                ->retry((int) config('project_updater.retries', 2), 500, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($this->absoluteUrl('updates/check'), $payload);
        } catch (ConnectionException $e) {
            Log::channel('single')->warning('[license] update server unreachable', ['error' => $e->getMessage()]);

            return ['outcome' => 'unreachable', 'status' => 0, 'message' => $e->getMessage()];
        }

        $status = $response->status();

        // 401/403 are the only confirmed-invalid signals (bad token or domain
        // mismatch). Everything else ambiguous is treated as transient so a
        // genuine buyer is never punished for a flaky network or 5xx.
        if (in_array($status, [401, 403], true)) {
            return ['outcome' => 'invalid', 'status' => $status, 'message' => $response->json('message')];
        }

        if ($response->successful() && ($response->json('success') ?? true) !== false) {
            return ['outcome' => 'valid', 'status' => $status, 'message' => null];
        }

        return ['outcome' => 'unreachable', 'status' => $status, 'message' => $response->json('message')];
    }

    public function download(string $url): string
    {
        $response = Http::timeout((int) config('project_updater.timeout', 20))
            ->retry((int) config('project_updater.retries', 2), 500, throw: false)
            ->accept('application/zip')
            ->get($this->absoluteUrl($url));

        if ($response->failed()) {
            throw new NotifyErrorException(__('The update package could not be downloaded. Server returned :status.', [
                'status' => $response->status(),
            ]));
        }

        return $response->body();
    }

    /**
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $endpoint, array $payload): array
    {
        $serverUrl = config('project_updater.server_url');

        if (blank($serverUrl)) {
            throw new NotifyErrorException(__('Project updater server URL is not configured.'));
        }

        $url = $this->absoluteUrl($endpoint);

        Log::channel('single')->info('[updater] outgoing request', [
            'endpoint' => $endpoint,
            'url'      => $url,
            'payload'  => $this->safePayload($payload),
        ]);

        try {
            $response = Http::timeout((int) config('project_updater.timeout', 20))
                ->retry((int) config('project_updater.retries', 2), 500, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::channel('single')->error('[updater] connection failed', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new NotifyErrorException(__('Project updater server is unreachable right now.'));
        }

        Log::channel('single')->info('[updater] response', [
            'url'     => $url,
            'status'  => $response->status(),
            'body'    => Str::limit((string) $response->body(), 2000),
            'headers' => $response->headers(),
        ]);

        if ($response->status() === 429) {
            throw new NotifyErrorException(__('Updater rate limit reached. Try again after :seconds seconds.', [
                'seconds' => $response->header('Retry-After', 'a few'),
            ]));
        }

        if ($response->failed()) {
            $message = $response->json('message') ?: __('Project updater request failed.');

            Log::channel('single')->error('[updater] request failed', [
                'url'     => $url,
                'status'  => $response->status(),
                'message' => $message,
            ]);

            throw new NotifyErrorException($message);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new NotifyErrorException(__('Project updater returned an invalid response.'));
        }

        if (($data['success'] ?? true) === false) {
            throw new NotifyErrorException((string) ($data['message'] ?? __('Project updater rejected the request.')));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        if (isset($payload['purchase_code']) && is_string($payload['purchase_code'])) {
            $payload['purchase_code'] = Str::mask($payload['purchase_code'], '*', 4, max(0, strlen($payload['purchase_code']) - 8));
        }

        if (isset($payload['license_token']) && is_string($payload['license_token'])) {
            $payload['license_token'] = Str::limit($payload['license_token'], 8, '…');
        }

        return $payload;
    }

    private function absoluteUrl(string $endpoint): string
    {
        if (Str::startsWith($endpoint, ['http://', 'https://'])) {
            return $endpoint;
        }

        return rtrim((string) config('project_updater.server_url'), '/').'/api/v1/'.ltrim($endpoint, '/');
    }
}
