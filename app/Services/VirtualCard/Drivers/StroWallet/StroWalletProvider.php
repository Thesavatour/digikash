<?php

namespace App\Services\VirtualCard\Drivers\StroWallet;

use App\Enums\VirtualCard\CardholderType;
use App\Enums\VirtualCard\VirtualCardNetwork;
use App\Exceptions\NotifyErrorException;
use App\Models\Cardholders;
use App\Models\PaymentGateway;
use App\Models\VirtualCard;
use App\Models\VirtualCardRequest;
use App\Services\VirtualCard\Drivers\AbstractVirtualCardProvider;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class StroWalletProvider extends AbstractVirtualCardProvider
{
    /**
     * Single base URL for every StroWallet endpoint. Sandbox vs live is
     * switched via the `mode` body/query parameter, not the URL path —
     * this matches the official SDK + readme docs.
     */
    private const API_BASE = 'https://strowallet.com/api';

    /**
     * Map our cardholder `id_type` vocabulary to the values StroWallet's
     * `/bitvcard/create-user` endpoint documents:
     *
     *     "type of ID e.g BVN, NIN, PASSPORT"
     *
     * Strowallet's KYC pipeline is Nigerian-first so it understands
     * BVN (Bank Verification Number) and NIN (National ID), then falls
     * back to PASSPORT for non-Nigerian residents. Uppercase values are
     * the documented form.
     *
     * @var array<string, string>
     */
    private const ID_TYPE_MAP = [
        'passport'         => 'PASSPORT',
        'national_id'      => 'NIN',
        'drivers_license'  => 'DRIVERS_LICENSE',
        'residence_permit' => 'PASSPORT', // Strowallet has no permit slot — passport is the safe equivalent for foreign residents
        'voter_id'         => 'NIN',      // Closest national-ID analogue
        'bvn'              => 'BVN',      // Allow Nigerian operators to opt in explicitly
        'nin'              => 'NIN',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $credentials;

    private Client $httpClient;

    private string $mode;

    public function __construct()
    {
        $this->credentials = PaymentGateway::getCredentials('strowallet');
        $this->httpClient  = new Client;

        $this->mode = $this->credentials['sandbox'] ? 'sandbox' : 'live';

        if (empty($this->credentials['public_key']) || empty($this->credentials['secret_key'])) {
            throw new Exception('StroWallet API credentials are not configured.');
        }
    }

    /**
     * StroWallet health probe — pings the customer-list endpoint with
     * the configured public key. A 200 (even with an empty list) means
     * the key is valid; anything else surfaces in the admin UI.
     *
     * Uses the same unified URL pattern as every other method on this
     * class (`/bitvcard/...` + `mode` query/body param). Mixing in a
     * `/api/sandbox/...` prefix here while the rest of the class used
     * `/api/...` produced a "test passes but issuance fails" trap.
     */
    public function testConnection(): array
    {
        $started = microtime(true);
        try {
            $response = $this->httpClient->get(self::API_BASE.'/bitvcard/list-customers/', [
                'query' => [
                    'public_key' => $this->credentials['public_key'],
                    'mode'       => $this->mode,
                ],
                'timeout' => 10,
            ]);
            $latency = (int) ((microtime(true) - $started) * 1000);
            $body    = json_decode($response->getBody()->getContents(), true);
            $ok      = $response->getStatusCode() === 200
                && $this->responseLooksSuccessful($body);

            return [
                'ok'      => $ok,
                'mode'    => $this->mode,
                'message' => $ok
                    ? __('StroWallet credentials accepted. Reachable in :ms ms.', ['ms' => $latency])
                    : (string) ($body['message'] ?? __('StroWallet returned a non-success status.')),
                'latency_ms' => $latency,
                'details'    => [
                    'base_url'    => self::API_BASE,
                    'status_code' => $response->getStatusCode(),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok'         => false,
                'mode'       => $this->mode,
                'message'    => __('StroWallet connection failed: :err', ['err' => $e->getMessage()]),
                'latency_ms' => (int) ((microtime(true) - $started) * 1000),
                'details'    => ['exception' => get_class($e)],
            ];
        }
    }

    public function issueCard(VirtualCardRequest $request): array
    {
        $customerData = $this->resolveCustomerData($request);

        $customerResponse = $this->createCustomer($customerData);

        // The card is embossed with the CARDHOLDER's legal name, not the
        // user-account holder's. These can differ when a user creates a
        // card on behalf of a family member, employee, etc. The previous
        // `$request->user->name` would put the parent's name on a child's
        // card — wrong, and Strowallet's KYC would reject it.
        $nameOnCard = trim($customerData['firstName'].' '.($customerData['lastName'] ?? ''));

        $cardData = [
            'name_on_card'  => $nameOnCard,
            'card_type'     => $this->resolveCardType($request),
            'public_key'    => $this->credentials['public_key'],
            'amount'        => $request->initial_load_amount,
            'customerEmail' => $this->customerEmailForCardCreation($customerData['customerEmail']),
            'mode'          => $this->mode,
        ];

        $cardResponse = $this->createCard($cardData);

        return [
            'id'           => $cardResponse['card_id'],
            'last4'        => $cardResponse['last4'],
            'brand'        => $cardResponse['brand'],
            'expiry_month' => $cardResponse['expiry_month'],
            'expiry_year'  => $cardResponse['expiry_year'],
            'status'       => $cardResponse['status'],
            'meta'         => $cardResponse['meta'],
            // Raw API responses persisted on `virtual_card_requests.provider_response`
            // by VirtualCardManager. Invaluable when debugging "why did the issuance
            // status come back like this?" — the previous implementation always saved
            // null here because the `raw` key was never returned.
            'raw' => [
                'customer' => $customerResponse,
                'card'     => $cardResponse['raw'] ?? null,
            ],
        ];
    }

    /**
     * Resolve the StroWallet `card_type` value from the request's network.
     *
     * Falls back to `visa` when no network is set (legacy rows from before
     * the network column was required) — StroWallet's sandbox issues only
     * Visa cards regardless, so this remains the safest default.
     */
    private function resolveCardType(VirtualCardRequest $request): string
    {
        $network = $request->network instanceof VirtualCardNetwork
            ? $request->network->value
            : (string) $request->network;

        return $network !== '' ? strtolower($network) : 'visa';
    }

    /**
     * Pick the customerEmail used when calling `/bitvcard/create-card/`.
     *
     * StroWallet's sandbox historically accepts only a small set of demo
     * customer emails. Keep that override behaviour but make it
     * configurable so each environment can pin its own sandbox account.
     *
     * Live mode always uses the real cardholder email.
     */
    private function customerEmailForCardCreation(string $real): string
    {
        if ($this->mode !== 'sandbox') {
            return $real;
        }

        $sandboxEmail = trim((string) ($this->credentials['sandbox_customer_email'] ?? ''));

        return $sandboxEmail !== '' ? $sandboxEmail : 'mydemo@gmail.com';
    }

    public function topUpCard($amount, $cardID): array
    {
        try {
            $response = $this->httpClient->post(self::API_BASE.'/bitvcard/fund-card/', [
                'json' => [
                    'card_id'    => $cardID,
                    'amount'     => $amount,
                    'public_key' => $this->credentials['public_key'],
                    'mode'       => $this->mode,
                ],
                'headers' => [
                    'accept'       => 'application/json',
                    'content-type' => 'application/json',
                ],
                'timeout' => 15,
            ]);

            $responseData = json_decode($response->getBody(), true);
            $this->assertResponseOk($responseData, 'StroWallet top-up failed.');

            // Return the relevant API response data for DB save or further logic
            return [
                'id'        => $responseData['apiresponse']['data']['id']        ?? null,
                'status'    => $responseData['apiresponse']['data']['status']    ?? null,
                'cardId'    => $responseData['apiresponse']['data']['cardId']    ?? null,
                'reference' => $responseData['apiresponse']['data']['reference'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('StroWallet top-up exception', ['error' => $e->getMessage()]);
            throw new Exception('StroWallet API top-up error: '.$e->getMessage());
        }
    }

    public function withdrawFromCard($amount, $cardID): array
    {
        try {
            $response = $this->httpClient->post(self::API_BASE.'/bitvcard/card_withdraw/', [
                'json' => [
                    'card_id'    => $cardID,
                    'amount'     => $amount,
                    'public_key' => $this->credentials['public_key'],
                    'mode'       => $this->mode,
                ],
                'headers' => [
                    'accept'       => 'application/json',
                    'content-type' => 'application/json',
                ],
                'timeout' => 15,
            ]);

            $responseData = json_decode($response->getBody(), true);
            $this->assertResponseOk($responseData, 'StroWallet withdraw failed.');

            // Return the relevant API response data for DB save or further logic
            return [
                'id'        => $responseData['apiresponse']['data']['id']        ?? null,
                'status'    => $responseData['apiresponse']['data']['status']    ?? null,
                'cardId'    => $responseData['apiresponse']['data']['cardId']    ?? null,
                'reference' => $responseData['apiresponse']['data']['reference'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('StroWallet withdraw exception', ['error' => $e->getMessage()]);
            throw new Exception('StroWallet API withdraw error: '.$e->getMessage());
        }
    }

    public function getCardDetails(VirtualCard $card)
    {
        $providerCardId = $card->meta['card_id'] ?? $card->provider_card_id;

        if (! $providerCardId) {
            throw new NotifyErrorException(__('Provider card id is missing for this card.'));
        }

        try {
            $details = $this->fetchCardDetails($providerCardId)['card_detail'] ?? null;

            if (! $details) {
                throw new NotifyErrorException(__('Failed to retrieve card details.'));
            }

            return [
                'card_name'        => $details['card_name']         ?? null,
                'card_status'      => $details['card_status']       ?? $card->status?->value,
                'card_brand'       => $details['card_brand']        ?? $card->brand,
                'card_number'      => $details['card_number']       ?? null,
                'cvv'              => $details['cvv']               ?? null,
                'expiry'           => $details['expiry']            ?? null,
                'card_holder_name' => $details['card_holder_name']  ?? null,
                'card_type'        => $details['card_type']         ?? null,
                'balance'          => $details['balance']           ?? 0,
                'created_at'       => $details['card_created_date'] ?? null,
                'billing_street'   => $details['billing_street']    ?? null,
                'billing_city'     => $details['billing_city']      ?? null,
                'billing_country'  => $details['billing_country']   ?? null,
                'billing_zip_code' => $details['billing_zip_code']  ?? null,
                'customer_email'   => $details['customer_email']    ?? null,
                'reference'        => $details['reference']         ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('StroWallet card details fetch error', ['error' => $e->getMessage()]);
            throw new NotifyErrorException(__('Card details API error: :msg', ['msg' => $e->getMessage()]));
        }
    }

    private function resolveCustomerData(VirtualCardRequest $request): array
    {
        $ch         = Cardholders::with('business')->with('user')->findOrFail($request->cardholder_id);
        $isBusiness = $ch->card_type === CardholderType::BUSINESS;

        if ($isBusiness) {
            throw new NotifyErrorException('StroWallet only supports personal accounts, not business accounts.');
        }

        // Provider-universal source of truth: structured columns first,
        // legacy KYC-template fall-through second. Existing rows that
        // were saved via the old template flow keep working.
        $rawIdType  = $ch->id_type ?: optional($ch->kycTemplate)->title;
        $idDocument = $ch->kyc_documents['id_document'] ?? null;
        if (! $idDocument && optional($ch->kycTemplate)->fields) {
            $legacyFile = collect($ch->kycTemplate->fields)->firstWhere('type', 'file');
            if ($legacyFile) {
                $idDocument = $ch->kyc_documents[$legacyFile['label']] ?? null;
            }
        }

        if (! $rawIdType || ! $idDocument) {
            throw new NotifyErrorException('Cardholder is missing the Government ID type or document image.');
        }

        if (empty($ch->id_number)) {
            throw new NotifyErrorException('Cardholder is missing the Government ID number.');
        }

        // Map our wider id_type vocabulary onto StroWallet's documented set
        // (PASSPORT, NIN, BVN, DRIVERS_LICENSE). PASSPORT is the safe
        // fallback for unknown values because every operator accepts it.
        $mappedIdType = self::ID_TYPE_MAP[strtolower($rawIdType)] ?? 'PASSPORT';

        $idImageUrl = asset($idDocument);
        // Cardholder selfie lives in `kyc_documents` (uploaded as part
        // of the cardholder form). Fall back to the user-account avatar
        // only for legacy rows that pre-date the cardholder selfie
        // upload, so user-on-behalf-of-cardholder flows put the right
        // person's face on the KYC packet.
        $cardholderSelfie = $ch->kyc_documents['selfie']
            ?? $ch->kyc_documents['user_photo']
            ?? $ch->kyc_documents['photo']
            ?? null;
        $userPhotoUrl = $cardholderSelfie ? asset($cardholderSelfie) : asset($ch->user->avatar_alt);

        // StroWallet fetches both images server-side. If APP_URL points at
        // localhost / private IPs / .test / .local then their servers
        // can't reach the files, customer creation fails with a generic
        // "could not download image" error that's painful to diagnose.
        // Catch it here with a message that names the actual problem.
        $this->assertImageUrlReachable($idImageUrl, 'Government ID image');
        $this->assertImageUrlReachable($userPhotoUrl, 'User selfie / avatar');

        return [
            'public_key'    => $this->credentials['public_key'],
            'firstName'     => $ch->first_name,
            'lastName'      => $ch->last_name,
            'customerEmail' => $ch->email,
            // StroWallet doc: "mobile number in international format
            // without +". A common shape on our side is `+8801712345678`
            // — strip the `+` and any spaces/dashes so the API sees a
            // pure digit string.
            'phoneNumber' => $this->normalizePhoneNumber($ch->mobile, $ch->phone_country_code),
            'dob'         => $ch->dob ? (new DateTime($ch->dob))->format('m/d/Y') : null,
            'idImage'     => $idImageUrl,
            'userPhoto'   => $userPhotoUrl,
            'street'      => $ch->address_line1,
            'state'       => $ch->state,
            'zipCode'     => $ch->postal_code,
            'city'        => $ch->city,
            'country'     => $ch->country,
            'idType'      => $mappedIdType,
            'idNumber'    => $ch->id_number,
        ];

    }

    /**
     * Normalize a phone number to "international format without +" as
     * required by StroWallet's `/bitvcard/create-user` endpoint.
     *
     * Cases handled:
     *   `+8801712345678`, `null`         → `8801712345678`     (just strip +)
     *   `+1 (415) 555-2671`, `null`      → `14155552671`       (strip formatting)
     *   `01712345678`, `+880`            → `88001712345678`    (prepend country)
     *   `8801712345678`, `+880`          → `8801712345678`     (don't double-prepend)
     *
     * Heuristic: a string starting with `+` is treated as international.
     * A string starting with the supplied country code (digits only) is
     * also treated as international. Everything else is treated as
     * local and the country code is prepended.
     */
    private function normalizePhoneNumber(?string $mobile, ?string $countryCode = null): string
    {
        $mobile = trim((string) $mobile);
        if ($mobile === '') {
            return '';
        }

        $countryDigits = preg_replace('/\D+/', '', (string) $countryCode);

        $isInternational = str_starts_with($mobile, '+')
            || ($countryDigits !== '' && str_starts_with(preg_replace('/\D+/', '', $mobile), $countryDigits));

        if (! $isInternational && $countryDigits !== '') {
            $mobile = $countryDigits.$mobile;
        }

        // Strip every non-digit (covers `+`, spaces, dashes, parens).
        return preg_replace('/\D+/', '', $mobile) ?? '';
    }

    /**
     * Reject URLs StroWallet's servers can't fetch from the public internet.
     *
     * Detects the common dev-time mistake of running issuance from a
     * machine whose `APP_URL` is `http://localhost`, `http://127.0.0.1`,
     * a `.test`/`.local` host, or a private RFC1918 IP. In all of those
     * cases StroWallet returns a generic image-download failure and the
     * admin can't tell why.
     *
     * Only the host is checked — the scheme being http (vs https) is
     * fine for StroWallet, they accept both.
     *
     * @throws NotifyErrorException when the URL is not internet-reachable.
     */
    private function assertImageUrlReachable(?string $url, string $label): void
    {
        if (! $url) {
            throw new NotifyErrorException(__(':label is missing.', ['label' => $label]));
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            throw new NotifyErrorException(__(':label URL is malformed.', ['label' => $label]));
        }

        $host  = strtolower($host);
        $isDev = $host === 'localhost'
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost');

        if (! $isDev && filter_var($host, FILTER_VALIDATE_IP)) {
            // Private / reserved IP ranges are not reachable from outside.
            $isDev = ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        if ($isDev) {
            throw new NotifyErrorException(__(
                ':label URL (:url) is not reachable from the public internet. '
                .'StroWallet downloads images server-side, so APP_URL must point '
                .'at a publicly-accessible domain (e.g. your tunnel or staging URL).',
                ['label' => $label, 'url' => $url]
            ));
        }
    }

    private function createCustomer(array $data): array
    {
        // Trailing slash matches the canonical URL in StroWallet's
        // "Create customer" reference page
        // (https://strowallet.readme.io/reference/create-customer):
        //     https://strowallet.com/api/bitvcard/create-user/
        // Some hosts redirect a slash-less request via a 301, which
        // Guzzle then follows but the body is dropped, leading to
        // "Missing required field" errors.
        $response = $this->httpClient->post(self::API_BASE.'/bitvcard/create-user/', [
            'json' => [
                'public_key'    => $data['public_key'],
                'mode'          => $this->mode,
                'houseNumber'   => $data['street'],
                'firstName'     => $data['firstName'],
                'lastName'      => $data['lastName'] ?? '',
                'idNumber'      => $data['idNumber'],
                'customerEmail' => $data['customerEmail'],
                'phoneNumber'   => $data['phoneNumber'],
                'dateOfBirth'   => $data['dob'] ?? '',
                'idImage'       => $data['idImage'],
                'userPhoto'     => $data['userPhoto'],
                'line1'         => $data['street'],
                'state'         => $data['state'],
                'zipCode'       => $data['zipCode'],
                'city'          => $data['city'],
                'country'       => $data['country'],
                'idType'        => $data['idType'],
            ],
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        $response_data = json_decode($response->getBody(), true);
        $this->assertResponseOk($response_data, 'StroWallet customer creation failed.');

        return $response_data;
    }

    private function createCard(array $data): array
    {

        $response = $this->httpClient->post(self::API_BASE.'/bitvcard/create-card/', [
            'json' => [
                'name_on_card'  => $data['name_on_card'],
                'card_type'     => $data['card_type'],
                'public_key'    => $data['public_key'],
                'amount'        => $data['amount'],
                'customerEmail' => $data['customerEmail'],
                'mode'          => $data['mode'],
            ],
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $response_data = json_decode($response->getBody(), true);
        $this->assertResponseOk($response_data, 'StroWallet card issuance failed.');

        $rawResponse   = $response_data;
        $response_data = $response_data['response'];

        $cardDetails = $this->fetchCardDetails($response_data['card_id'])['card_detail'];

        $meta = [
            'card_id'      => $cardDetails['card_id'],
            'card_user_id' => $cardDetails['card_user_id'],
            'customer_id'  => $cardDetails['customer_id'],
        ];
        [$expiryMonth, $expiryYear] = explode('/', $cardDetails['expiry']);

        return [
            'card_id'      => $cardDetails['card_id'],
            'last4'        => $cardDetails['last4'],
            'brand'        => $cardDetails['card_brand'],
            'expiry_month' => $expiryMonth,
            'expiry_year'  => $expiryYear,
            'status'       => $response_data['card_status'],
            'meta'         => $meta,
            'raw'          => $rawResponse,
        ];
    }

    /**
     * Freeze (block) a StroWallet card.
     *
     * Hits `POST /bitvcard/action/status` with `action=freeze`. StroWallet's
     * own dashboard calls the same endpoint — verified against the
     * elite/strowallet-sdk reference implementation. On success the
     * controller (callsite) still flips our local DB status; we just
     * report `soft=false` so the UI doesn't show the "freeze didn't
     * propagate to the provider" hint.
     */
    public function freezeCard(VirtualCard $card): array
    {
        $this->setCardStatus($card, 'freeze');

        return ['status' => 'inactive', 'soft' => false];
    }

    /**
     * Unfreeze (re-activate) a StroWallet card via the same status endpoint.
     */
    public function unfreezeCard(VirtualCard $card): array
    {
        $this->setCardStatus($card, 'unfreeze');

        return ['status' => 'active', 'soft' => false];
    }

    /**
     * @param 'freeze'|'unfreeze' $action
     */
    private function setCardStatus(VirtualCard $card, string $action): void
    {
        $providerCardId = $card->meta['card_id'] ?? $card->provider_card_id;
        if (! $providerCardId) {
            throw new NotifyErrorException(__('Provider card id is missing for this card.'));
        }

        $response = $this->httpClient->post(self::API_BASE.'/bitvcard/action/status/', [
            'json' => [
                'public_key' => $this->credentials['public_key'],
                'mode'       => $this->mode,
                'card_id'    => $providerCardId,
                'action'     => $action,
            ],
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        $body = json_decode($response->getBody(), true);
        $this->assertResponseOk(
            $body,
            $action === 'freeze'
                ? 'StroWallet freeze failed.'
                : 'StroWallet unfreeze failed.'
        );
    }

    private function fetchCardDetails(string $cardId): array
    {
        $response = $this->httpClient->post(self::API_BASE.'/bitvcard/fetch-card-detail/', [
            'json' => [
                'public_key' => $this->credentials['public_key'],
                'mode'       => $this->mode,
                'card_id'    => $cardId,
            ],
            'headers' => [
                'accept'       => 'application/json',
                'content-type' => 'application/json',
            ],
            'timeout' => 20,
        ]);

        $response_data = json_decode($response->getBody(), true);
        $this->assertResponseOk($response_data, 'StroWallet card details fetch failed.');

        return $response_data['response'];
    }

    /**
     * Unified StroWallet response gate.
     *
     * Modern endpoints return `{"success": false, "message": "..."}` on
     * failure (e.g. "Cannot create card for RU user"), while legacy
     * endpoints sometimes return `{"error": "not ok", ...}`. The
     * previous code only checked the `error` shape, so the modern
     * `success: false` failures slipped past the guard and crashed
     * downstream when the caller dereferenced a missing `response` key.
     *
     * Treat the response as failed when ANY of these are true:
     *   - `success` is present and falsy
     *   - `error` is present and not "ok"
     *   - `message` is present alongside a falsy `success`
     *
     * @throws Exception with the provider's own message when available.
     */
    private function assertResponseOk(?array $body, string $fallbackMessage): void
    {
        if (! is_array($body)) {
            Log::error('StroWallet response was not JSON.', ['body' => $body]);
            throw new Exception($fallbackMessage);
        }

        if ($this->responseLooksSuccessful($body)) {
            return;
        }

        $providerMessage = trim((string) ($body['message'] ?? $body['error'] ?? ''));
        Log::error('StroWallet API returned a failure response.', ['response' => $body]);

        throw new Exception($providerMessage !== '' ? $providerMessage : $fallbackMessage);
    }

    /**
     * Decide whether a parsed StroWallet body represents success.
     *
     * Used by both `assertResponseOk()` (throwing path) and
     * `testConnection()` (non-throwing diagnostic path) so they apply
     * the same rules.
     */
    private function responseLooksSuccessful(?array $body): bool
    {
        if (! is_array($body)) {
            return false;
        }

        if (array_key_exists('success', $body) && ! $body['success']) {
            return false;
        }

        if (array_key_exists('error', $body)) {
            $err = strtolower((string) $body['error']);
            if ($err !== '' && $err !== 'ok' && $err !== 'false') {
                return false;
            }
        }

        return true;
    }
}
