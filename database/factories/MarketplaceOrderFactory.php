<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\CurrencyType;
use App\Enums\Marketplace\OrderStatus;
use App\Models\Currency;
use App\Models\MarketplaceOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketplaceOrder>
 */
class MarketplaceOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id'          => User::factory(),
            'vendor_id'         => Vendor::factory(),
            'currency_id'       => fn () => $this->resolveCurrencyId(),
            'gross_amount'      => 100,
            'commission_amount' => 5,
            'vendor_net'        => 95,
            'status'            => OrderStatus::PENDING->value,
            'provider'          => 'wallet',
        ];
    }

    private function resolveCurrencyId(): int
    {
        $currency = Currency::query()->where('default', true)->first()
            ?? Currency::query()->first();

        if (! $currency) {
            $currency = Currency::create([
                'name'          => 'US Dollar',
                'code'          => 'USD',
                'symbol'        => '$',
                'type'          => CurrencyType::FIAT,
                'exchange_rate' => 1,
                'rate_live'     => false,
                'default'       => true,
                'status'        => true,
            ]);
        }

        return (int) $currency->id;
    }
}
