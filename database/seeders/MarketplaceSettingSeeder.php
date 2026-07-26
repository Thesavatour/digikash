<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MarketplaceSetting;
use Illuminate\Database\Seeder;

class MarketplaceSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (MarketplaceSetting::query()->exists()) {
            return;
        }

        MarketplaceSetting::query()->create([
            'enabled'             => false,
            'commission_pct'      => 5.0000,
            'commission_fixed'    => 0,
            'min_order_amount'    => 1,
            'max_order_amount'    => null,
            'auto_release'        => true,
            'payout_hold_minutes' => 0,
            'commission_user_id'  => null,
        ]);
    }
}
