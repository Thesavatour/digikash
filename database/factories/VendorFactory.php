<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Marketplace\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'display_name' => fake()->company(),
            'status'       => VendorStatus::ACTIVE->value,
            'about'        => fake()->sentence(),
            'approved_at'  => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'      => VendorStatus::PENDING->value,
            'approved_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => VendorStatus::SUSPENDED->value]);
    }
}
