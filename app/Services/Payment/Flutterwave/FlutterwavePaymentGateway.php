<?php

namespace App\Services\Payment\Flutterwave;

use App\Enums\TrxStatus;
use App\Exceptions\NotifyErrorException;
use App\Models\PaymentGateway;
use App\Models\Transaction as TransactionModel;
use App\Services\Payment\PaymentGateway as PaymentGatewayInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Transaction;

class FlutterwavePaymentGateway implements PaymentGatewayInterface
{
    private const FLUTTERWAVE_API_URL = 'https://api.flutterwave.com/v3';

    /**
     * Flutterwave's documented terminal statuses for verified transactions.
     *
     * @see https://developer.flutterwave.com/v3.0/docs/transaction-verification
     */
    private const SUCCESSFUL_STATUSES = ['successful'];

    private const FAILED_STATUSES = ['failed', 'cancelled', 'canceled'];

    protected Client $client;

    private string $publicKey;

    private string $secretKey;

    private ?string $secretHash;

    public function __construct()
    {
        $credentials = PaymentGateway::getCredentials('flutterwave');

        if (empty($credentials['secret_key']) || empty($credentials['public_key'])) {
            throw new Exception('Flutterwave API credentials are not set.');
        }

        $this->publicKey  = (string) $credentials['public_key'];
        $this->secretKey  = (string) $credentials['secret_key'];
        $this->secretHash = ! empty($credentials['secret_hash']) ? (string) $credentials['secret_hash'] : null;

        $this->client = new Client([
            'headers' => [
                'Authorization' => 'Bearer '.$this->secretKey,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function deposit($amount, $currency, $trxId)
    {
        $transaction = TransactionModel::where('trx_id', $trxId)->first();
        $trxData     = $transaction?->trx_data ?? [];
        $user        = auth()->user();

        $customerEmail = trim((string) ($trxData['customer_email'] ?? $user?->email ?? ''));
        if ($customerEmail === '' || ! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $host          = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com';
            $fallback      = 'customer@'.$host;
            $customerEmail = filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : 'customer@example.com';
        }

        $customerName  = trim((string) ($trxData['customer_name'] ?? $user?->name ?? 'Customer'));
        $customerPhone = trim((string) ($trxData['customer_phone'] ?? $user?->phone ?? ''));

        $customer = [
            'email' => $customerEmail,
            'name'  => $customerName !== '' ? $customerName : 'Customer',
        ];

        if ($customerPhone !== '') {
            $customer['phonenumber'] = $customerPhone;
        }

        $paymentPayload = [
            'tx_ref'         => $trxId,
            'amount'         => $amount,
            'currency'       => $currency,
            'redirect_url'   => route('status.success'),
            'customer'       => $customer,
            'customizations' => [
                'title'       => setting('site_title'),
                'description' => 'Payment for order '.$trxId,
            ],
            'meta' => [
                'trx_id' => $trxId,
            ],
        ];

        try {
            $response = $this->client->post(self::FLUTTERWAVE_API_URL.'/payments', [
                'json' => $paymentPayload,
            ]);

            $payment = json_decode($response->getBody(), true);

            if (! empty($payment['data']['link'])) {
                return $payment['data']['link'];
            }

            Log::error('Flutterwave: Invalid payment response', is_array($payment) ? $payment : ['response' => $payment]);

            throw new NotifyErrorException(__('Failed to initiate Flutterwave payment. Please try again.'));
        } catch (GuzzleException $e) {
            $response  = $e->getResponse();
            $errorBody = $response ? $response->getBody()->getContents() : null;
            Log::error('Flutterwave Payment Error: '.$e->getMessage(), ['response' => $errorBody]);

            throw new NotifyErrorException(__('Flutterwave payment initiation failed. Please try again or contact support.'));
        }
    }

    public function handleIPN(Request $request): JsonResponse
    {
        // 1. Verify the webhook signature using the `verif-hash` header.
        //    Flutterwave returns the secret hash configured in the dashboard
        //    directly in the header — there's no HMAC step.
        //    https://developer.flutterwave.com/docs/webhooks
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('Flutterwave webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // 2. Only process completed-charge events; ack everything else with 200
        //    so Flutterwave doesn't retry endlessly.
        $event = (string) $request->input('event', '');
        if ($event !== 'charge.completed') {
            Log::info('Flutterwave: unhandled webhook event', ['event' => $event]);

            return response()->json(['message' => 'Event not handled'], 200);
        }

        // 3. Validate payload shape.
        $data = $request->input('data');
        if (! is_array($data) || ! isset($data['id'], $data['tx_ref'])) {
            return response()->json(['error' => 'Invalid webhook payload'], 400);
        }

        $transactionId = (int) $data['id'];
        $txRef         = (string) $data['tx_ref'];

        // 4. Idempotency guard — bail early if we've already finalized this trx.
        $transaction = TransactionModel::where('trx_id', $txRef)->first();
        if (! $transaction) {
            Log::info('Flutterwave webhook: unknown tx_ref, ignoring', ['tx_ref' => $txRef]);

            return response()->json(['status' => 'ignored']);
        }

        if ($transaction->status !== TrxStatus::PENDING) {
            return response()->json(['status' => 'already_processed']);
        }

        try {
            // 5. Independently verify the transaction with Flutterwave's API.
            //    Webhook bodies are not authoritative; always re-fetch.
            $response = $this->client->get(self::FLUTTERWAVE_API_URL."/transactions/{$transactionId}/verify");
            $result   = json_decode($response->getBody(), true);

            $verifiedStatus = strtolower((string) ($result['data']['status'] ?? ''));
            $verifiedRef    = (string) ($result['data']['tx_ref'] ?? '');
            $verifiedAmount = (float) ($result['data']['amount'] ?? 0);
            $verifiedCurr   = strtoupper((string) ($result['data']['currency'] ?? ''));

            // 6. Defence-in-depth: ensure the verified transaction matches what
            //    we expect — tx_ref, amount and currency. Prevents replay or
            //    tampered webhooks from completing the wrong order.
            if ($verifiedRef !== $txRef) {
                Log::warning('Flutterwave webhook: tx_ref mismatch', [
                    'expected' => $txRef,
                    'verified' => $verifiedRef,
                ]);

                return response()->json(['error' => 'Reference mismatch'], 400);
            }

            $expectedAmount   = (float) $transaction->payable_amount;
            $expectedCurrency = strtoupper((string) $transaction->payable_currency);

            if (in_array($verifiedStatus, self::SUCCESSFUL_STATUSES, true)) {
                if ($verifiedAmount + 0.01 < $expectedAmount || $verifiedCurr !== $expectedCurrency) {
                    Log::warning('Flutterwave webhook: amount/currency mismatch', [
                        'tx_ref'            => $txRef,
                        'expected_amount'   => $expectedAmount,
                        'verified_amount'   => $verifiedAmount,
                        'expected_currency' => $expectedCurrency,
                        'verified_currency' => $verifiedCurr,
                    ]);

                    Transaction::failTransaction($txRef, 'Flutterwave amount or currency mismatch.');

                    return response()->json(['status' => 'amount_mismatch'], 400);
                }

                Transaction::completeTransaction($txRef);

                return response()->json(['status' => 'success']);
            }

            if (in_array($verifiedStatus, self::FAILED_STATUSES, true)) {
                Transaction::failTransaction($txRef, 'Flutterwave reported status: '.$verifiedStatus);

                return response()->json(['status' => 'failed']);
            }

            // Anything else (e.g. "pending") — don't terminate the transaction.
            // Flutterwave will resend the webhook once it settles.
            Log::info('Flutterwave webhook: non-terminal status, leaving pending', [
                'tx_ref' => $txRef,
                'status' => $verifiedStatus,
            ]);

            return response()->json(['status' => 'pending']);
        } catch (GuzzleException $e) {
            Log::error('Flutterwave Webhook Verification Error', [
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
                'tx_ref'         => $txRef,
            ]);

            // Return 5xx so Flutterwave retries.
            return response()->json(['error' => 'Verification failed'], 502);
        }
    }

    /**
     * Verify the `verif-hash` header against the configured secret hash.
     *
     * Flutterwave returns the dashboard-configured secret hash directly in
     * the `verif-hash` header — there is no HMAC, just a constant-time
     * string compare. If no secret_hash is configured we log a warning and
     * reject the webhook, since accepting unverified webhooks would let any
     * caller mark transactions as paid.
     *
     * @see https://developer.flutterwave.com/docs/webhooks
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        if ($this->secretHash === null || $this->secretHash === '') {
            Log::warning('Flutterwave webhook rejected: secret_hash credential is not configured.');

            return false;
        }

        $signature = (string) $request->header('verif-hash', '');

        return $signature !== '' && hash_equals($this->secretHash, $signature);
    }
}
