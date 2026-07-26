<?php

namespace App\Services\Payment\MercadoPago;

use App\Enums\TrxStatus;
use App\Models\PaymentGateway as PaymentGatewayModel;
use App\Models\Transaction as TransactionModel;
use App\Services\Payment\PaymentGateway as PaymentGatewayInterface;
use App\Services\TransactionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MercadoPagoPaymentGateway implements PaymentGatewayInterface
{
    private const BASE_URL = 'https://api.mercadopago.com';

    private const APPROVED_STATUSES = ['approved'];

    private const FAILED_STATUSES = ['rejected', 'cancelled', 'canceled', 'refunded', 'charged_back'];

    private const ZERO_DECIMAL_CURRENCIES = ['CLP', 'COP', 'PYG'];

    /**
     * @var array<string, mixed>
     */
    private array $credentials;

    private string $accessToken;

    private ?string $webhookSecret;

    private bool $sandbox;

    public function __construct()
    {
        $this->credentials   = PaymentGatewayModel::getCredentials('mercadopago');
        $this->accessToken   = $this->credential('access_token');
        $this->webhookSecret = $this->nullableCredential('webhook_secret');
        $this->sandbox       = filter_var($this->credentials['sandbox'] ?? false, FILTER_VALIDATE_BOOL);

        if ($this->accessToken === '') {
            throw new Exception('Mercado Pago access token is not configured.');
        }
    }

    public function deposit($amount, $currency, $trxId)
    {
        $currency    = strtoupper((string) $currency);
        $transaction = TransactionModel::query()->where('trx_id', $trxId)->first();
        $payload     = $this->preferencePayload((float) $amount, $currency, (string) $trxId, $transaction?->trx_data ?? []);
        $preference  = $this->post('/checkout/preferences', $payload);
        $checkoutUrl = $this->checkoutUrl($preference);

        if ($checkoutUrl === '') {
            Log::error('Mercado Pago preference response missing checkout URL', [
                'transaction_id' => $trxId,
                'response'       => $this->redactForLog($preference),
            ]);

            throw new Exception('Mercado Pago checkout URL missing.');
        }

        $this->mergeTransactionData((string) $trxId, [
            'mercado_pago_preference' => $this->preferenceForStorage($preference),
        ], $this->scalarString($preference['id'] ?? null));

        return $checkoutUrl;
    }

    public function handleIPN(Request $request): JsonResponse
    {
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('Mercado Pago webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $this->payloadFromRequest($request);
        $type    = $this->notificationType($request, $payload);

        if ($type !== '' && $type !== 'payment') {
            return response()->json(['status' => 'ignored']);
        }

        $paymentId = $this->extractPaymentId($request, $payload);

        if ($paymentId === null) {
            Log::warning('Mercado Pago webhook missing payment id', [
                'payload' => $this->redactForLog($payload),
                'query'   => $request->query(),
            ]);

            return response()->json(['error' => 'Missing payment id'], 400);
        }

        try {
            $payment = $this->get('/v1/payments/'.$paymentId);
            $trxId   = $this->scalarString($payment['external_reference'] ?? null);

            if ($trxId === null) {
                Log::info('Mercado Pago webhook ignored because external_reference is missing', [
                    'payment_id' => $paymentId,
                ]);

                return response()->json(['status' => 'ignored']);
            }

            $transaction = app(TransactionService::class)->findTransaction($trxId);

            if (! $transaction) {
                Log::info('Mercado Pago webhook ignored because transaction was not found', [
                    'payment_id' => $paymentId,
                    'trx_id'     => $trxId,
                ]);

                return response()->json(['status' => 'ignored']);
            }

            $this->mergeTransactionData($trxId, [
                'mercado_pago_payment' => $this->paymentForStorage($payment),
            ], $this->scalarString($payment['id'] ?? $paymentId));

            if ($transaction->status !== TrxStatus::PENDING) {
                return response()->json(['status' => 'success']);
            }

            $status = strtolower((string) ($payment['status'] ?? ''));

            if (in_array($status, self::APPROVED_STATUSES, true)) {
                if (! $this->paymentMatchesTransaction($payment, $transaction)) {
                    app(TransactionService::class)->failTransaction(
                        $trxId,
                        'Mercado Pago amount or currency mismatch.'
                    );

                    return response()->json(['status' => 'amount_mismatch'], 400);
                }

                app(TransactionService::class)->completeTransaction(
                    $trxId,
                    'Mercado Pago payment approved.'
                );

                return response()->json(['status' => 'success']);
            }

            if (in_array($status, self::FAILED_STATUSES, true)) {
                app(TransactionService::class)->failTransaction(
                    $trxId,
                    'Mercado Pago payment status: '.$status
                );

                return response()->json(['status' => 'failed']);
            }

            return response()->json(['status' => 'pending']);
        } catch (Throwable $e) {
            Log::error('Mercado Pago webhook processing failed', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Verification failed'], 502);
        }
    }

    /**
     * @param  array<string, mixed> $trxData
     * @return array<string, mixed>
     */
    private function preferencePayload(float $amount, string $currency, string $trxId, array $trxData): array
    {
        return [
            'items' => [
                [
                    'id'          => $trxId,
                    'title'       => 'DigiKash Payment '.$trxId,
                    'description' => 'Payment for transaction '.$trxId,
                    'quantity'    => 1,
                    'currency_id' => $currency,
                    'unit_price'  => $this->normalizeAmount($amount, $currency),
                ],
            ],
            'payer'              => $this->payerPayload($trxData),
            'back_urls'          => $this->backUrls($trxId),
            'auto_return'        => 'approved',
            'notification_url'   => route('ipn.handle', ['gateway' => 'mercadopago', 'source_news' => 'webhooks']),
            'external_reference' => $trxId,
            'metadata'           => [
                'trx_id' => $trxId,
            ],
        ];
    }

    /**
     * @param  array<string, mixed> $trxData
     * @return array<string, mixed>
     */
    private function payerPayload(array $trxData): array
    {
        $user      = auth()->user();
        $fullName  = trim((string) ($trxData['customer_name'] ?? $user?->name ?? 'Customer'));
        $email     = trim((string) ($trxData['customer_email'] ?? $user?->email ?? ''));
        $phone     = trim((string) ($trxData['customer_phone'] ?? $user?->phone ?? ''));
        $nameParts = preg_split('/\s+/', $fullName) ?: [];

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $host  = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';
            $email = 'customer@'.$host;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = 'customer@example.com';
            }
        }

        $payer = [
            'email'      => $email,
            'first_name' => $nameParts[0] ?? 'Customer',
            'last_name'  => $nameParts[1] ?? 'NA',
        ];

        if ($phone !== '') {
            $payer['phone'] = [
                'number' => $phone,
            ];
        }

        return $payer;
    }

    /**
     * @return array<string, string>
     */
    private function backUrls(string $trxId): array
    {
        return [
            'success' => route('status.callback', ['gateway' => 'mercadopago', 'trx' => $trxId, 'result' => 'success']),
            'failure' => route('status.callback', ['gateway' => 'mercadopago', 'trx' => $trxId, 'result' => 'failure']),
            'pending' => route('status.callback', ['gateway' => 'mercadopago', 'trx' => $trxId, 'result' => 'pending']),
        ];
    }

    private function checkoutUrl(array $preference): string
    {
        $preferred = $this->sandbox
            ? $this->scalarString($preference['sandbox_init_point'] ?? null)
            : $this->scalarString($preference['init_point'] ?? null);

        return $preferred
            ?? $this->scalarString($preference['init_point'] ?? null)
            ?? $this->scalarString($preference['sandbox_init_point'] ?? null)
            ?? '';
    }

    /**
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $url = self::BASE_URL.$path;

        try {
            $http = Http::withToken($this->accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->connectTimeout(10);

            $response = $method === 'GET'
                ? $http->get($url)
                : $http->post($url, $payload);
        } catch (Throwable $e) {
            Log::error('Mercado Pago transport error', [
                'method' => $method,
                'path'   => $path,
                'error'  => $e->getMessage(),
            ]);

            throw new Exception('Mercado Pago request failed: '.$e->getMessage(), previous: $e);
        }

        $json = $response->json();

        if (! is_array($json)) {
            $json = ['raw' => $response->body()];
        }

        if ($response->failed()) {
            $message = $this->extractErrorMessage($json) ?? 'Mercado Pago API error';

            Log::warning('Mercado Pago API failure', [
                'method'  => $method,
                'path'    => $path,
                'status'  => $response->status(),
                'message' => $message,
                'body'    => $this->redactForLog($json),
            ]);

            throw new Exception($message);
        }

        return $json;
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        if ($this->webhookSecret === null || $this->isPlaceholderValue($this->webhookSecret)) {
            Log::warning('Mercado Pago webhook rejected: webhook_secret credential is not configured.');

            return false;
        }

        $signature = $this->parseSignatureHeader((string) $request->header('x-signature', ''));
        $ts        = $signature['ts'] ?? '';
        $hash      = $signature['v1'] ?? '';

        if ($ts === '' || $hash === '') {
            return false;
        }

        $manifest = $this->signatureManifest(
            $this->signatureDataId($request),
            (string) $request->header('x-request-id', ''),
            $ts
        );

        return hash_equals(hash_hmac('sha256', $manifest, $this->webhookSecret), $hash);
    }

    /**
     * @return array<string, string>
     */
    private function parseSignatureHeader(string $header): array
    {
        $signature = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

            $key   = trim((string) $key);
            $value = trim((string) $value);

            if ($key !== '' && $value !== '') {
                $signature[$key] = $value;
            }
        }

        return $signature;
    }

    private function signatureDataId(Request $request): string
    {
        $query  = $request->query();
        $dataId = $query['data.id'] ?? $query['data_id'] ?? null;

        if (! is_scalar($dataId)) {
            return '';
        }

        $dataId = trim((string) $dataId);

        return ctype_alnum($dataId) ? strtolower($dataId) : $dataId;
    }

    private function signatureManifest(string $dataId, string $requestId, string $ts): string
    {
        $parts = [];

        if ($dataId !== '') {
            $parts[] = 'id:'.$dataId;
        }

        if ($requestId !== '') {
            $parts[] = 'request-id:'.$requestId;
        }

        if ($ts !== '') {
            $parts[] = 'ts:'.$ts;
        }

        return implode(';', $parts).';';
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromRequest(Request $request): array
    {
        $payload = $request->json()->all();

        if (is_array($payload) && $payload !== []) {
            return $payload;
        }

        $decoded = json_decode((string) $request->getContent(), true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $request->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function notificationType(Request $request, array $payload): string
    {
        $type = $request->query('type')
            ?: $request->input('type')
            ?: $request->input('topic')
            ?: data_get($payload, 'type');

        return strtolower(trim((string) $type));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractPaymentId(Request $request, array $payload): ?string
    {
        $query = $request->query();

        $candidates = [
            $query['data.id'] ?? null,
            $query['data_id'] ?? null,
            $request->query('id'),
            data_get($payload, 'data.id'),
            $request->input('id'),
        ];

        $resource = $request->input('resource') ?: data_get($payload, 'resource');
        if (is_scalar($resource)) {
            $path = parse_url((string) $resource, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $candidates[] = basename($path);
            }
        }

        foreach ($candidates as $candidate) {
            $value = $this->scalarString($candidate);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function paymentMatchesTransaction(array $payment, TransactionModel $transaction): bool
    {
        $amount   = (float) ($payment['transaction_amount'] ?? 0);
        $currency = strtoupper((string) ($payment['currency_id'] ?? ''));

        return abs($amount - (float) $transaction->payable_amount) <= 0.01
            && $currency === strtoupper((string) $transaction->payable_currency);
    }

    private function normalizeAmount(float $amount, string $currency): float|int
    {
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        return round($amount, 2);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mergeTransactionData(string $trxId, array $data, ?string $reference = null): void
    {
        $transaction = TransactionModel::query()->where('trx_id', $trxId)->first();

        if (! $transaction) {
            return;
        }

        $updates = [
            'trx_data' => array_merge($transaction->trx_data ?? [], $data),
        ];

        if ($reference !== null) {
            $updates['trx_reference'] = $reference;
        }

        $transaction->update($updates);
    }

    /**
     * @param  array<string, mixed> $preference
     * @return array<string, mixed>
     */
    private function preferenceForStorage(array $preference): array
    {
        return collect($preference)
            ->only(['id', 'init_point', 'sandbox_init_point', 'date_created', 'external_reference'])
            ->all();
    }

    /**
     * @param  array<string, mixed> $payment
     * @return array<string, mixed>
     */
    private function paymentForStorage(array $payment): array
    {
        return collect($payment)
            ->only([
                'id',
                'status',
                'status_detail',
                'external_reference',
                'transaction_amount',
                'currency_id',
                'payment_method_id',
                'payment_type_id',
                'date_approved',
                'date_last_updated',
            ])
            ->all();
    }

    private function credential(string $key): string
    {
        $value = $this->nullableCredential($key);

        if ($value === null || $this->isPlaceholderValue($value)) {
            return '';
        }

        return $value;
    }

    private function nullableCredential(string $key): ?string
    {
        if (! isset($this->credentials[$key]) || ! is_scalar($this->credentials[$key])) {
            return null;
        }

        $value = trim((string) $this->credentials[$key]);

        return $value !== '' ? $value : null;
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function isPlaceholderValue(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, [
            'access_token',
            'webhook_secret',
            'your_access_token',
            'your_webhook_secret',
            'prod_access_token',
            'test_access_token',
        ], true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractErrorMessage(array $payload): ?string
    {
        foreach (['message', 'error', 'reason', 'detail'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_array($value)) {
                $message = $this->extractErrorMessage($value);

                if ($message !== null) {
                    return $message;
                }
            }
        }

        $cause = $payload['cause'] ?? null;

        if (is_array($cause)) {
            return collect($cause)
                ->map(function (mixed $item): ?string {
                    return is_array($item) ? $this->extractErrorMessage($item) : null;
                })
                ->filter()
                ->implode(' | ') ?: null;
        }

        return null;
    }

    private function redactForLog(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $key => $item) {
            $keyString      = is_string($key) ? strtolower($key) : (string) $key;
            $redacted[$key] = str_contains($keyString, 'secret')
                || str_contains($keyString, 'token')
                || str_contains($keyString, 'authorization')
                    ? '[redacted]'
                    : $this->redactForLog($item);
        }

        return $redacted;
    }
}
