<?php

namespace Database\Seeders;

use App\Enums\PayoutMethod;
use App\Models\RemittanceCorridor;
use Illuminate\Database\Seeder;

class RemittanceCorridorSeeder extends Seeder
{
    public function run(): void
    {
        $corridors = [
            [
                'name'                   => 'USD → BDT (Bangladesh)',
                'source_country'         => 'US',
                'source_currency'        => 'USD',
                'destination_country'    => 'BD',
                'destination_currency'   => 'BDT',
                'allowed_payout_methods' => [
                    PayoutMethod::BankAccount->value,
                    PayoutMethod::MobileWallet->value,
                ],
                'payout_gateway'    => 'manual',
                'fx_spread_percent' => 0.75,
                'fixed_fee'         => 1.99,
                'percent_fee'       => 0.5,
                'min_amount'        => 5,
                'max_amount'        => 5000,
                'quote_ttl_minutes' => 15,
                'required_kyc_tier' => 1,
                'status'            => true,
            ],
            [
                'name'                   => 'USD → INR (India)',
                'source_country'         => 'US',
                'source_currency'        => 'USD',
                'destination_country'    => 'IN',
                'destination_currency'   => 'INR',
                'allowed_payout_methods' => [
                    PayoutMethod::BankAccount->value,
                ],
                'payout_gateway'    => 'manual',
                'fx_spread_percent' => 0.85,
                'fixed_fee'         => 2.49,
                'percent_fee'       => 0.6,
                'min_amount'        => 10,
                'max_amount'        => 7500,
                'quote_ttl_minutes' => 15,
                'required_kyc_tier' => 1,
                'status'            => true,
            ],
            [
                'name'                   => 'USD → NGN (Nigeria)',
                'source_country'         => 'US',
                'source_currency'        => 'USD',
                'destination_country'    => 'NG',
                'destination_currency'   => 'NGN',
                'allowed_payout_methods' => [
                    PayoutMethod::BankAccount->value,
                    PayoutMethod::MobileWallet->value,
                ],
                'payout_gateway'    => 'manual',
                'fx_spread_percent' => 1.0,
                'fixed_fee'         => 2.99,
                'percent_fee'       => 0.8,
                'min_amount'        => 10,
                'max_amount'        => 10000,
                'quote_ttl_minutes' => 15,
                'required_kyc_tier' => 1,
                'status'            => true,
            ],
            [
                'name'                   => 'USD → PHP (Philippines)',
                'source_country'         => 'US',
                'source_currency'        => 'USD',
                'destination_country'    => 'PH',
                'destination_currency'   => 'PHP',
                'allowed_payout_methods' => [
                    PayoutMethod::BankAccount->value,
                    PayoutMethod::MobileWallet->value,
                    PayoutMethod::CashPickup->value,
                ],
                'payout_gateway'    => 'manual',
                'fx_spread_percent' => 0.9,
                'fixed_fee'         => 2.49,
                'percent_fee'       => 0.7,
                'min_amount'        => 10,
                'max_amount'        => 10000,
                'quote_ttl_minutes' => 15,
                'required_kyc_tier' => 1,
                'status'            => true,
            ],
        ];

        foreach ($corridors as $attributes) {
            RemittanceCorridor::query()->firstOrCreate(
                [
                    'source_country'       => $attributes['source_country'],
                    'source_currency'      => $attributes['source_currency'],
                    'destination_country'  => $attributes['destination_country'],
                    'destination_currency' => $attributes['destination_currency'],
                ],
                $attributes,
            );
        }
    }
}
