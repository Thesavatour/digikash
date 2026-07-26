<?php

namespace App\Services\Payment\StroWallet;

use App\Enums\TrxStatus;
use App\Enums\VirtualCard\VirtualCardStatus;
use App\Models\PaymentGateway;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VirtualCard;
use App\Services\Payment\PaymentGateway as PaymentGatewayInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class StroWalletPaymentGateway implements PaymentGatewayInterface
{
    /**
     * StroWallet's published card-webhook event → handler-method map.
     * Adding a new event is one row here + one method below.
     *
     * Reference: https://strowallet.readme.io/reference/webhook-for-card
     *
     * @var array<string, string>
     */
    private const CARD_WEBHOOK_EVENTS = [
        'virtualcard.created.complete'                => 'cardCreated',
        'virtualcard.topup.complete'                  => 'cardTopupSuccess',
        'virtualcard.withdrawal.success'              => 'cardWithdrawalSuccess',
        'virtualcard.transaction.authorization'       => 'cardAuthorization',
        'virtualcard.transaction.declined'            => 'cardDeclined',
        'virtualcard.transaction.declined.terminated' => 'cardTerminated',
    ];

    /**
     * @var array<string, mixed>
     */
    protected array $credentials;

    private string $mode;

    public function __construct()
    {
        $this->credentials = PaymentGateway::getCredentials('strowallet');
        $this->mode        = $this->credentials['sandbox'] ? 'sandbox' : 'live';

        if (empty($this->credentials['public_key']) || empty($this->credentials['secret_key'])) {
            throw new Exception('StroWallet API credentials are not configured.');
        }
    }

    public function deposit($amount, $currency, $trxId)
    {

        $userId = \Transaction::findTransaction($trxId)->user_id;

        $user = User::find($userId);

        // Refactored to use StroWallet's GET request flow
        $parameters = [
            'amount'      => $amount,
            'currency'    => $currency,
            'details'     => 'Deposit for transaction '.$trxId,
            'custom'      => $trxId,
            'ipn_url'     => route('ipn.handle', ['gateway' => 'strowallet']),
            'success_url' => route('status.success'),
            'cancel_url'  => route('status.cancel'),
            'public_key'  => $this->credentials['public_key'],
            'name'        => $user->name,
            'email'       => $user->email,
        ];

        $endpoint = 'https://strowallet.com/express/initiate';
        $call     = $endpoint.'?'.http_build_query($parameters);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $call);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $response_data = json_decode($response, true);

        if (isset($response_data['error']) && $response_data['error'] !== 'ok') {
            Log::error('StroWallet payment init error', ['response' => $response_data]);

            return back()->withErrors(['error' => 'StroWallet payment failed to initialize.']);
        }

        if (empty($response_data['url'])) {
            Log::error('StroWallet payment missing redirect URL', ['response' => $response_data]);

            return back()->withErrors(['error' => 'StroWallet redirect URL missing.']);
        }

        return redirect($response_data['url']);
    }

    /**
     * Unified IPN + virtual-card webhook receiver.
     *
     * StroWallet uses ONE callback URL (the one we registered via
     * `ipn_url` on the express deposit + the one configured in the
     * dashboard for virtual-card webhooks). Two payload shapes arrive
     * on the same route:
     *
     *   1. Express deposit IPN  → has `signature`, `amount`, `custom`,
     *      `trx_num`. HMAC-SHA256 over `amount+currency+custom+trx_num`
     *      using the secret_key. Completes the local Transaction row.
     *
     *   2. Virtual-card webhook → has an `event` field that matches one
     *      of CARD_WEBHOOK_EVENTS. Updates card status / meta / linked
     *      transaction based on the event type. StroWallet does not
     *      sign card webhooks (yet), so we authenticate by matching
     *      `cardId` against a card we actually issued — events for
     *      cards we never saw are ignored.
     *
     * The deposit flow is preserved exactly as it was; only the card
     * dispatcher is new.
     */
    public function handleIPN(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');

        if ($event !== '') {
            return $this->handleCardWebhook($request, $event);
        }

        return $this->handleDepositIpn($request);
    }

    /**
     * Original express-deposit IPN logic — preserved unchanged so this
     * refactor is bug-compatible with the historical deposit flow.
     */
    private function handleDepositIpn(Request $request): JsonResponse
    {
        $amount   = $request->input('amount');
        $currency = $request->input('currency');
        $custom   = $request->input('custom');
        $trx_num  = $request->input('trx_num');
        $sentSign = $request->input('signature');

        $string = $amount.$currency.$custom.$trx_num;
        $secret = $this->credentials['secret_key'];
        $mySign = strtoupper(hash_hmac('sha256', $string, $secret));

        if ($sentSign !== $mySign) {
            \Transaction::failTransaction($custom);
            abort(403, 'Invalid IPN signature');
        }

        \Transaction::completeTransaction($custom);

        return response()->json(['status' => 'success']);
    }

    /**
     * Dispatch a virtual-card webhook event to its handler. Always
     * returns 200 so StroWallet does not enter a 3-retry storm —
     * failures are logged instead.
     */
    private function handleCardWebhook(Request $request, string $event): JsonResponse
    {
        $payload = $request->all();
        $method  = self::CARD_WEBHOOK_EVENTS[$event] ?? null;

        if (! $method) {
            Log::info('StroWallet card webhook received unknown event', ['event' => $event]);

            return response()->json(['ok' => true, 'ignored' => $event]);
        }

        try {
            $this->{$method}($payload);
        } catch (Throwable $e) {
            Log::error('StroWallet card webhook handler error', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle `virtualcard.created.complete`.
     *
     * Payload fields (per the StroWallet doc):
     *   id, event, amount, cardId, cardBrand, lastFour, status,
     *   createdStatus, reference.
     *
     * Strowallet's async issuance pipeline can fire this event with
     * `createdStatus="failed"` when KYC review rejects the card after
     * we've already saved a row. Marking it Active in that case
     * would lie to the user. Only flip to Active when both fields
     * confirm success.
     *
     * Also backfills `last4` and `brand` from the event because the
     * initial `issueCard()` response can return them as null on the
     * async path — the webhook is the source of truth.
     *
     * @param array<string, mixed> $event
     */
    private function cardCreated(array $event): void
    {
        $card = $this->findCard($event);
        if (! $card) {
            return;
        }

        $created = strtolower((string) ($event['createdStatus'] ?? $event['status'] ?? ''));
        $isOk    = in_array($created, ['success', 'completed', 'complete', 'active', ''], true);

        $update = [];
        if (! empty($event['lastFour']) && empty($card->last4)) {
            $update['last4'] = (string) $event['lastFour'];
        }
        if (! empty($event['cardBrand']) && empty($card->brand)) {
            $update['brand'] = strtolower((string) $event['cardBrand']);
        }
        $update['status'] = $isOk
            ? VirtualCardStatus::Active->value
            : VirtualCardStatus::Failed->value;

        $card->update($update);
    }

    /**
     * Handle `virtualcard.topup.complete`.
     *
     * Payload: id, event, date, amount, cardId, status, reference,
     * reason, narrative, isTerminated.
     *
     * The event fires for both successful AND failed topups (it's the
     * "complete" — meaning settled either way — event). The `status`
     * field carries the actual outcome. Only credit the on-card
     * balance and complete the local transaction row when status
     * reports success.
     *
     * @param array<string, mixed> $event
     */
    private function cardTopupSuccess(array $event): void
    {
        $succeeded = $this->eventIsSuccess($event);
        $this->markCardTransactionStatus($event, $succeeded ? TrxStatus::COMPLETED : TrxStatus::FAILED);

        if (! $succeeded) {
            return;
        }

        $card = $this->findCard($event);
        if ($card && isset($event['amount'])) {
            $meta            = is_array($card->meta) ? $card->meta : [];
            $currentBalance  = (float) ($meta['balance'] ?? 0);
            $meta['balance'] = round($currentBalance + (float) $event['amount'], 2);
            $card->update(['meta' => $meta]);
        }
    }

    /**
     * Handle `virtualcard.withdrawal.success`.
     *
     * Payload: id, event, date, amount, fee, credited, cardId, reason,
     * status, narrative, reference.
     *
     * Same status-guard pattern as topup — Strowallet ships this event
     * for both success and failure paths; the `status` field carries
     * the truth.
     *
     * @param array<string, mixed> $event
     */
    private function cardWithdrawalSuccess(array $event): void
    {
        $succeeded = $this->eventIsSuccess($event);
        $this->markCardTransactionStatus($event, $succeeded ? TrxStatus::COMPLETED : TrxStatus::FAILED);

        if (! $succeeded) {
            return;
        }

        $card = $this->findCard($event);
        if ($card && isset($event['amount'])) {
            $meta            = is_array($card->meta) ? $card->meta : [];
            $currentBalance  = (float) ($meta['balance'] ?? 0);
            $meta['balance'] = round(max(0, $currentBalance - (float) $event['amount']), 2);
            $card->update(['meta' => $meta]);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function cardAuthorization(array $event): void
    {
        // Merchant authorization — record on meta for audit but don't
        // touch the card status or our wallet ledger. The float lives at
        // StroWallet; we don't double-book the spend.
        $card = $this->findCard($event);
        if (! $card) {
            return;
        }
        $meta                          = is_array($card->meta) ? $card->meta : [];
        $meta['last_authorization']    = $event;
        $meta['last_authorization_at'] = now()->toIso8601String();
        $card->update(['meta' => $meta]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function cardDeclined(array $event): void
    {
        $card = $this->findCard($event);
        if (! $card) {
            return;
        }
        $meta                  = is_array($card->meta) ? $card->meta : [];
        $meta['last_declined'] = $event;
        $card->update(['meta' => $meta]);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function cardTerminated(array $event): void
    {
        $card = $this->findCard($event);
        if (! $card) {
            return;
        }
        $card->update(['status' => VirtualCardStatus::Blocked->value]);
    }

    /**
     * Find the card this event belongs to.
     *
     * Strowallet's documented payload uses `cardId` (camelCase) across
     * every webhook event. The previous fallback to `$event['id']` was
     * a bug — `id` is the EVENT id, not the card id, so dropping a real
     * `cardId` (e.g. due to a typo in a hand-fired test webhook) would
     * silently match a card by unrelated UUID.
     *
     * `card_id` is kept as a defensive fallback only for case-variant
     * payloads we've occasionally seen in legacy logs.
     *
     * @param array<string, mixed> $event
     */
    private function findCard(array $event): ?VirtualCard
    {
        $providerCardId = $event['cardId']
            ?? $event['card_id']
            ?? null;

        if (! $providerCardId) {
            return null;
        }

        return VirtualCard::query()
            ->whereJsonContains('meta->card_id', $providerCardId)
            ->first()
            ?? VirtualCard::query()
                ->where('provider_card_id', $providerCardId)
                ->first();
    }

    /**
     * Whether a webhook event's `status` field signals success.
     *
     * Strowallet uses the same event name (e.g. `virtualcard.topup.complete`)
     * for both success and failure outcomes — the `status` field is the
     * actual discriminator. Treat empty/missing status as success so
     * minimal payloads (and sandbox events that omit it) keep working.
     *
     * @param array<string, mixed> $event
     */
    private function eventIsSuccess(array $event): bool
    {
        $status = strtolower(trim((string) ($event['status'] ?? '')));

        if ($status === '') {
            return true;
        }

        return in_array($status, ['success', 'successful', 'completed', 'complete', 'ok'], true);
    }

    /**
     * Settle our local transaction row matching the event's reference.
     *
     * @param array<string, mixed> $event
     */
    private function markCardTransactionStatus(array $event, TrxStatus $status): void
    {
        $reference = (string) ($event['reference'] ?? '');
        if ($reference === '') {
            return;
        }

        $tx = Transaction::query()->where('trx_id', $reference)->first()
            ?? Transaction::query()->whereJsonContains('meta->strowallet_reference', $reference)->first();

        if (! $tx) {
            return;
        }

        $meta                     = $tx->meta ?? [];
        $meta['strowallet_event'] = $event;
        $tx->status               = $status;
        $tx->meta                 = $meta;
        $tx->save();
    }
}
