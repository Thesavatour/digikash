<?php

namespace Database\Factories;

use App\Enums\PayoutMethod;
use App\Models\RemittanceCorridor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemittanceCorridor>
 */
class RemittanceCorridorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pair = fake()->unique()->randomElement([
            ['BD', 'BDT'],
            ['IN', 'INR'],
            ['PK', 'PKR'],
            ['PH', 'PHP'],
            ['NG', 'NGN'],
            ['KE', 'KES'],
            ['GH', 'GHS'],
            ['UG', 'UGX'],
            ['ZA', 'ZAR'],
            ['MX', 'MXN'],
        ]);

        return [
            'name'                   => sprintf('USD → %s', $pair[1]),
            'source_country'         => 'US',
            'source_currency'        => 'USD',
            'destination_country'    => $pair[0],
            'destination_currency'   => $pair[1],
            'allowed_payout_methods' => [
                PayoutMethod::BankAccount->value,
                PayoutMethod::MobileWallet->value,
            ],
            'payout_gateway'    => 'manual',
            'fx_spread_percent' => 0.5,
            'fixed_fee'         => 1.99,
            'percent_fee'       => 0.5,
            'min_amount'        => 5,
            'max_amount'        => 5000,
            'quote_ttl_minutes' => 15,
            'required_kyc_tier' => 1,
            'status'            => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }
}
