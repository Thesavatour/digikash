<?php

namespace App\Enums;

use ValueError;

enum PayoutMethod: string
{
    case BankAccount  = 'bank_account';
    case MobileWallet = 'mobile_wallet';
    case CashPickup   = 'cash_pickup';
    case DebitCard    = 'debit_card';
    case WalletCredit = 'wallet_credit';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn ($case) => $case->value, self::cases()),
            array_map(fn ($case) => $case->label(), self::cases())
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::BankAccount  => __('Bank Account'),
            self::MobileWallet => __('Mobile Wallet'),
            self::CashPickup   => __('Cash Pickup'),
            self::DebitCard    => __('Debit Card'),
            self::WalletCredit => __('Wallet Credit'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BankAccount  => 'fa-solid fa-building-columns',
            self::MobileWallet => 'fa-solid fa-mobile-screen-button',
            self::CashPickup   => 'fa-solid fa-money-bill-wave',
            self::DebitCard    => 'fa-solid fa-credit-card',
            self::WalletCredit => 'fa-solid fa-wallet',
        };
    }

    /**
     * Country-specific field schema each payout method expects in the
     * beneficiary `account_details` JSON. Lets the UI render the right
     * inputs and the validator enforce required keys.
     *
     * @return array<int, array{name: string, label: string, required: bool}>
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::BankAccount => [
                ['name' => 'bank_name', 'label' => __('Bank Name'), 'required' => true],
                ['name' => 'account_number', 'label' => __('Account Number / IBAN'), 'required' => true],
                ['name' => 'swift_code', 'label' => __('SWIFT / BIC'), 'required' => false],
                ['name' => 'branch', 'label' => __('Branch'), 'required' => false],
            ],
            self::MobileWallet => [
                ['name' => 'provider', 'label' => __('Provider (bKash / M-Pesa / etc.)'), 'required' => true],
                ['name' => 'phone_number', 'label' => __('Mobile Number'), 'required' => true],
            ],
            self::CashPickup => [
                ['name' => 'partner', 'label' => __('Pickup Partner'), 'required' => true],
                ['name' => 'pickup_country', 'label' => __('Pickup Country'), 'required' => true],
            ],
            self::DebitCard => [
                ['name' => 'card_number', 'label' => __('Card Number'), 'required' => true],
                ['name' => 'card_holder', 'label' => __('Card Holder Name'), 'required' => true],
            ],
            self::WalletCredit => [
                ['name' => 'wallet_uuid', 'label' => __('Wallet UUID / Email'), 'required' => true],
            ],
        };
    }

    public static function getBadgesColor(string|array $method): string
    {
        if (is_array($method)) {
            $method = $method[0];
        }

        try {
            return match (self::from($method)) {
                self::BankAccount, self::WalletCredit => 'primary',
                self::MobileWallet => 'success',
                self::CashPickup   => 'warning',
                self::DebitCard    => 'info',
            };
        } catch (ValueError) {
            return 'secondary';
        }
    }
}
