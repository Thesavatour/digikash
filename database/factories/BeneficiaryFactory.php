<?php

namespace Database\Factories;

use App\Enums\PayoutMethod;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'first_name'      => fake()->firstName(),
            'last_name'       => fake()->lastName(),
            'nickname'        => null,
            'country_code'    => fake()->randomElement(['BD', 'IN', 'PK', 'PH', 'NG', 'KE']),
            'currency'        => fake()->randomElement(['BDT', 'INR', 'PKR', 'PHP', 'NGN', 'KES']),
            'payout_method'   => PayoutMethod::BankAccount,
            'phone_number'    => fake()->e164PhoneNumber(),
            'email'           => fake()->safeEmail(),
            'account_details' => [
                'bank_name'      => fake()->company().' Bank',
                'account_number' => fake()->bankAccountNumber(),
                'swift_code'     => strtoupper(fake()->bothify('????##??')),
            ],
            'relationship' => fake()->randomElement(['Family', 'Friend', 'Self', 'Business']),
            'is_favorite'  => false,
            'is_verified'  => true,
        ];
    }

    public function mobileWallet(): static
    {
        return $this->state(fn (): array => [
            'payout_method'   => PayoutMethod::MobileWallet,
            'account_details' => [
                'provider'     => fake()->randomElement(['bKash', 'M-Pesa', 'GCash']),
                'phone_number' => fake()->e164PhoneNumber(),
            ],
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (): array => ['is_favorite' => true]);
    }
}
