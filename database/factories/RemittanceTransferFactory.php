<?php

namespace Database\Factories;

use App\Enums\PayoutMethod;
use App\Enums\RemittanceStatus;
use App\Models\Beneficiary;
use App\Models\RemittanceCorridor;
use App\Models\RemittanceQuote;
use App\Models\RemittanceTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RemittanceTransfer>
 */
class RemittanceTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'              => User::factory(),
            'quote_id'             => RemittanceQuote::factory(),
            'beneficiary_id'       => Beneficiary::factory(),
            'corridor_id'          => RemittanceCorridor::factory(),
            'transaction_id'       => null,
            'source_currency'      => 'USD',
            'destination_currency' => 'BDT',
            'send_amount'          => 100,
            'fee_amount'           => 1.99,
            'total_paid'           => 101.99,
            'exchange_rate'        => 110.0,
            'receive_amount'       => 11000,
            'payout_method'        => PayoutMethod::BankAccount,
            'payout_gateway'       => 'manual',
            'status'               => RemittanceStatus::Pending,
            'purpose'              => 'family_support',
            'source_of_funds'      => 'salary',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status'       => RemittanceStatus::Completed,
            'submitted_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }

    public function failed(string $reason = 'Beneficiary account invalid'): static
    {
        return $this->state(fn (): array => [
            'status'         => RemittanceStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
