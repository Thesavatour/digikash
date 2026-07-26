<?php

declare(strict_types=1);

namespace Database\Factories\P2P;

use App\Models\P2P\OrderMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Order and sender have no factories of their own in this codebase, so callers
 * must always pass order_id and sender_id explicitly.
 *
 * @extends Factory<OrderMessage>
 */
class OrderMessageFactory extends Factory
{
    protected $model = OrderMessage::class;

    public function definition(): array
    {
        return [
            'body'    => fake()->sentence(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
