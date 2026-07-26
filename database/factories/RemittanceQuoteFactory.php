<?php

namespace Database\Factories;

use App\Enums\PayoutMethod;
use App\Models\RemittanceCorridor;
use App\Models\RemittanceQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemittanceQuote>
 */
class RemittanceQuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rate    = 110.0;
        $send    = 100.0;
        $fee     = 1.99;
        $receive = round($send * $rate, 2);

        return [
            'user_id'              => User::factory(),
            'corridor_id'          => RemittanceCorridor::factory(),
            'beneficiary_id'       => null,
            'source_currency'      => 'USD',
            'destination_currency' => 'BDT',
            'send_amount'          => $send,
            'fee_amount'           => $fee,
            'total_pay'            => $send + $fee,
            'exchange_rate'        => $rate,
            'mid_market_rate'      => $rate,
            'receive_amount'       => $receive,
            'payout_method'        => PayoutMethod::BankAccount,
            'expires_at'           => now()->addMinutes(15),
            'is_consumed'          => false,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => ['is_consumed' => true]);
    }
}
