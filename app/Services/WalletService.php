<?php

namespace App\Services;

use App\Constants\FixPctType;
use App\Exceptions\NotifyErrorException;
use App\Models\Currency;
use App\Models\FeatureAccessRule;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Wallet as WalletModel;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WalletService
{
    public const IDENTIFIER_EMAIL = 'email';

    public const IDENTIFIER_MOBILE = 'mobile';

    public const IDENTIFIER_WALLET_ID = 'wallet_id';

    private const WALLET_ID_PREFIX = 'DK';

    /**
     * @throws NotifyErrorException
     */
    public function getDefaultWallet(User $user)
    {
        $defaultCurrency = Currency::getDefault();

        if (! $defaultCurrency) {
            throw new NotifyErrorException('Default currency not found.');
        }

        return $user->wallets()->where('currency_id', $defaultCurrency->id)->first();
    }

    /**
     * Create a default wallet for a user if they don't have one with the default currency.
     */
    public function createDefaultWalletForUser(User $user): ?Wallet
    {
        // Fetch all auto wallet currencies
        $currencies = Currency::autoWallets();

        foreach ($currencies as $currency) {
            // Check if the user already has a wallet with this currency
            if (! $this->userHasWalletWithCurrency($user, $currency->id)) {
                $this->createWallet($user, $currency);
            }
        }

        return null; // Return null if no new wallet was created
    }

    /**
     * Create a wallet for a specified currency if the user doesn't already have one.
     */
    public function createWalletForCurrency(User $user, int $currencyId): ?Wallet
    {
        return ! $this->userHasWalletWithCurrency($user, $currencyId) ? $this->createWallet($user, Currency::findOrFail($currencyId)) : null;
    }

    /**
     * @throws Exception
     */
    public function subtractMoneyByWalletUuid($walletUuid, $amount): WalletModel
    {
        try {
            $wallet = $this->getWalletByUniqueId($walletUuid);
        } catch (ModelNotFoundException $e) {
            throw new NotifyErrorException("Wallet with UUID {$walletUuid} not found.");
        }

        return $this->subtractMoney($wallet, $amount);
    }

    /**
     * Retrieve a wallet by its UniqueWalletId.
     *
     * @throws Exception
     */
    public function getWalletByUniqueId(string $uuid): Wallet
    {
        $walletId = $this->normalizeWalletId($uuid);
        $wallet   = Wallet::where('uuid', $walletId)->first();

        if (! $wallet) {
            throw new NotifyErrorException(__("Wallet with ID $uuid not found."));
        }

        return $wallet;
    }

    /**
     * Subtract money from a wallet.
     *
     * @throws Exception
     */
    public function subtractMoney(Wallet $wallet, float $amount): Wallet
    {
        if ($amount <= 0) {
            throw new NotifyErrorException('Amount must be greater than zero.');
        }

        if ($wallet->balance < $amount) {
            throw new NotifyErrorException('Insufficient balance in wallet.');
        }

        $wallet->decrement('balance', $amount);

        return $wallet->refresh();
    }

    /**
     * @throws Exception
     */
    public function addMoneyByWalletUuid($walletUuid, $amount): WalletModel
    {
        try {
            $wallet = $this->getWalletByUniqueId($walletUuid);
        } catch (ModelNotFoundException $e) {
            throw new NotifyErrorException("Wallet with UUID {$walletUuid} not found.");
        }

        return $this->addMoney($wallet, $amount);
    }

    /**
     * Add money to a wallet.
     *
     * @throws Exception
     */
    public function addMoney(Wallet $wallet, float $amount): Wallet
    {
        if ($amount <= 0) {
            throw new NotifyErrorException('Amount must be greater than zero.');
        }

        $wallet->increment('balance', $amount);

        return $wallet->refresh();
    }

    public function getWalletByUserId(int $userId, string $currencyCode): ?Wallet
    {
        return Wallet::where('user_id', $userId)
            ->whereHas('currency', function ($query) use ($currencyCode) {
                $query->where('code', $currencyCode);
            })
            ->first();
    }

    public function getDefaultWalletByUserId(int $userId): ?Wallet
    {
        $currency = Currency::getDefault();

        return self::getWalletByUserId($userId, $currency->code);
    }

    public function isWalletBalanceSufficient($walletUuid, $amount): bool
    {
        $myWallet = $this->getWalletByUniqueId($walletUuid);

        $walletBalance = $this->getWalletBalance($myWallet);

        return $walletBalance >= $amount;
    }

    /**
     * Get a wallet's balance.
     */
    public function getWalletBalance(Wallet $wallet): float
    {
        return $wallet->balance;
    }

    /**
     * Retrieves a payer's wallet, given their identifier and currency ID.
     *
     * @throws Exception
     */
    public function getWalletByUserEmailOrWalletUid($emailOrWalletUid, $currencyId): ?WalletModel
    {
        if (filter_var($emailOrWalletUid, FILTER_VALIDATE_EMAIL)) {
            $recipientUser = User::where('email', $emailOrWalletUid)->first();

            return $recipientUser ? WalletModel::where('user_id', $recipientUser->id)->where('currency_id', $currencyId)->first() : null;
        }

        try {
            return self::getWalletByUniqueId((string) $emailOrWalletUid);
        } catch (NotifyErrorException) {
            return null;
        }
    }

    /**
     * Detect whether an identifier looks like an email, mobile number, or wallet ID.
     */
    public function detectIdentifierType(string $identifier): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return self::IDENTIFIER_EMAIL;
        }

        $stripped = preg_replace('/[\s\-\(\)]/', '', $identifier);

        if (preg_match('/^\+?[0-9]{8,15}$/', (string) $stripped)) {
            return self::IDENTIFIER_MOBILE;
        }

        return self::IDENTIFIER_WALLET_ID;
    }

    /**
     * Normalize a phone number input (strip whitespace, dashes, and parentheses).
     */
    public function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/[\s\-\(\)]/', '', trim($phone));
    }

    /**
     * Look up a recipient wallet by email.
     */
    public function getWalletByEmail(string $email, int $currencyId): ?WalletModel
    {
        $user = User::where('email', $email)->first();

        return $user ? WalletModel::where('user_id', $user->id)->where('currency_id', $currencyId)->first() : null;
    }

    /**
     * Find a user by phone number, matching with or without a leading "+" sign.
     * Wallet IDs always carry the "DK-" prefix, so a phone input without "+"
     * is never ambiguous with a wallet ID and can be safely accepted.
     */
    public function findUserByPhone(string $phone): ?User
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return null;
        }

        $variants = [$normalized];

        if (str_starts_with($normalized, '+')) {
            $variants[] = ltrim($normalized, '+');
        } else {
            $variants[] = '+'.$normalized;
        }

        return User::whereIn('phone', $variants)->first();
    }

    /**
     * Look up a recipient wallet by mobile number.
     */
    public function getWalletByPhone(string $phone, int $currencyId): ?WalletModel
    {
        $user = $this->findUserByPhone($phone);

        return $user ? WalletModel::where('user_id', $user->id)->where('currency_id', $currencyId)->first() : null;
    }

    /**
     * Resolve a recipient wallet by an arbitrary identifier (email / mobile / wallet ID),
     * subject to the methods allowed by the caller (e.g. feature access rules).
     *
     * @param array<int, string> $allowedMethods
     *
     * @throws Exception
     */
    public function getWalletByIdentifier(string $identifier, int $currencyId, array $allowedMethods = [
        self::IDENTIFIER_EMAIL,
        self::IDENTIFIER_MOBILE,
        self::IDENTIFIER_WALLET_ID,
    ]): ?WalletModel
    {
        $type = $this->detectIdentifierType($identifier);

        if (! in_array($type, $allowedMethods, true)) {
            return null;
        }

        return match ($type) {
            self::IDENTIFIER_EMAIL     => $this->getWalletByEmail($identifier, $currencyId),
            self::IDENTIFIER_MOBILE    => $this->getWalletByPhone($this->normalizePhone($identifier), $currencyId),
            self::IDENTIFIER_WALLET_ID => $this->safeGetWalletByUniqueId($identifier),
            default                    => null,
        };
    }

    private function safeGetWalletByUniqueId(string $uuid): ?WalletModel
    {
        try {
            return $this->getWalletByUniqueId($uuid);
        } catch (NotifyErrorException) {
            return null;
        }
    }

    /**
     * Resolve a recipient wallet for a feature/panel, honoring the admin-configured
     * identifier methods and verification requirements on the access rule.
     *
     * @return array{wallet: WalletModel|null, error: string|null}
     *
     * @throws Exception
     */
    public function resolveRecipientWallet(string $identifier, int $currencyId, ?FeatureAccessRule $rule): array
    {
        $allowedMethods = $rule?->allowedIdentifierMethods() ?? [
            self::IDENTIFIER_EMAIL,
            self::IDENTIFIER_MOBILE,
            self::IDENTIFIER_WALLET_ID,
        ];

        if ($allowedMethods === []) {
            return ['wallet' => null, 'error' => __('No recipient identifier methods are enabled for this feature.')];
        }

        $type = $this->detectIdentifierType($identifier);

        if (! in_array($type, $allowedMethods, true)) {
            return ['wallet' => null, 'error' => __('This identifier method is not allowed. Please use a permitted lookup method.')];
        }

        $wallet = $this->getWalletByIdentifier($identifier, $currencyId, $allowedMethods);

        if (! $wallet) {
            return ['wallet' => null, 'error' => __('Recipient wallet not found or invalid input provided.')];
        }

        $user = $wallet->user;

        if ($type === self::IDENTIFIER_EMAIL && $rule?->requiresVerifiedEmail() && $user?->email_verified_at === null) {
            return ['wallet' => null, 'error' => __('Recipient email is not verified.')];
        }

        if ($type === self::IDENTIFIER_MOBILE && $rule?->requiresVerifiedMobile() && ! ($user?->hasVerifiedPhone() ?? false)) {
            return ['wallet' => null, 'error' => __('Recipient mobile number is not verified.')];
        }

        return ['wallet' => $wallet, 'error' => null];
    }

    public function normalizeWalletId(string $walletId): string
    {
        $normalized = strtoupper(trim($walletId));
        $normalized = str_replace([' ', '_', '—', '–'], ['', '-', '-', '-'], $normalized);

        if (preg_match('/^'.self::WALLET_ID_PREFIX.'([A-Z0-9]{3})([A-F0-9]{4})([A-F0-9]{4})$/', $normalized, $matches)) {
            return self::WALLET_ID_PREFIX.'-'.$matches[1].'-'.$matches[2].'-'.$matches[3];
        }

        return $normalized;
    }

    public function formatMaskedWalletId(string $walletId): string
    {
        $normalized = $this->normalizeWalletId($walletId);

        if (preg_match('/^('.self::WALLET_ID_PREFIX.'-[A-Z0-9]+)-([A-Z0-9]{4})-([A-Z0-9]{4})$/', $normalized, $matches)) {
            return "{$matches[1]}-{$matches[2]}•••{$matches[3]}";
        }

        return $normalized;
    }

    /**
     * Determines if the payer and requester wallets are the same.
     */
    public function isSelfTransaction($formWallet, $toWallet): bool
    {
        return $formWallet->user_id === auth()->id() || $formWallet->id === $toWallet->id;
    }

    /**
     * Calculates the fee for requesting money, based on the requester's wallet and amount.
     */
    public function calculateFeeByRole($wallet, $amount, $role)
    {

        $currencyRole = $wallet->currency->roles()->where('role_name', $role)->first();

        return $currencyRole->fee_type === FixPctType::FIXED ? $currencyRole->fee : ($amount * $currencyRole->fee / 100);
    }

    public function conversionAmount($wallet, $amount)
    {
        $rate = $wallet->currency->exchange_rate;

        return $amount * $rate;
    }

    /**
     * @throws Exception
     */
    public function validateAmountLimitByRole($requesterWallet, $payableAmount, $role): void
    {
        $currencyRole = $requesterWallet->currency->roles()->where('role_name', $role)->first();

        if ($payableAmount < $currencyRole->min_limit || $payableAmount > $currencyRole->max_limit) {
            $message = __('Amount must be between :min and :max', ['min' => $currencyRole->min_limit, 'max' => $currencyRole->max_limit]);
            throw new NotifyErrorException($message);
        }

    }

    /**
     * Check if a user already has a wallet in a specific currency.
     */
    protected function userHasWalletWithCurrency(User $user, int $currencyId): bool
    {
        return $user->wallets()->where('currency_id', $currencyId)->exists();
    }

    /**
     * Create a wallet for a user with a given currency.
     */
    protected function createWallet(User $user, Currency $currency): Wallet
    {
        return Wallet::create([
            'currency_id' => $currency->id,
            'user_id'     => $user->id,
            'uuid'        => $this->generateUniqueWalletId($currency),
            'balance'     => 0.0,
            'status'      => true,
        ]);
    }

    /**
     * Generate a unique wallet ID.
     */
    protected function generateUniqueWalletId(Currency $currency): string
    {
        $currencyCode = $this->walletCurrencyCode($currency);

        do {
            $walletUuid = self::WALLET_ID_PREFIX.'-'.$currencyCode.'-'.$this->walletIdChunk().'-'.$this->walletIdChunk();
        } while (Wallet::where('uuid', $walletUuid)->exists());

        return $walletUuid;
    }

    private function walletCurrencyCode(Currency $currency): string
    {
        $currencyCode = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $currency->code));

        return $currencyCode !== '' ? $currencyCode : 'WLT';
    }

    private function walletIdChunk(): string
    {
        return strtoupper(str_pad(dechex(random_int(0, 65535)), 4, '0', STR_PAD_LEFT));
    }
}
