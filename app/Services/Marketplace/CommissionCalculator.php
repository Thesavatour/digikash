<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\MarketplaceSetting;
use App\Models\Vendor;

class CommissionCalculator
{
    /**
     * Pure commission math: percentage of gross plus an optional fixed fee,
     * capped so commission can never exceed the gross amount.
     *
     * @return array{commission: float, vendor_net: float, pct: float, fixed: float}
     */
    public function compute(float $gross, float $pct, float $fixed = 0.0): array
    {
        $commission = round($gross * ($pct / 100) + $fixed, 8);
        $commission = max(0.0, min($commission, $gross));
        $vendorNet  = round($gross - $commission, 8);

        return [
            'commission' => $commission,
            'vendor_net' => $vendorNet,
            'pct'        => $pct,
            'fixed'      => $fixed,
        ];
    }

    /**
     * Resolve the effective rates for a vendor (per-vendor override falls back
     * to the global marketplace setting) and compute the split.
     *
     * @return array{commission: float, vendor_net: float, pct: float, fixed: float}
     */
    public function forVendor(Vendor $vendor, float $gross): array
    {
        $settings = MarketplaceSetting::current();

        $pct = $vendor->commission_pct_override !== null
            ? (float) $vendor->commission_pct_override
            : (float) ($settings?->commission_pct ?? 0);

        $fixed = (float) ($settings?->commission_fixed ?? 0);

        return $this->compute($gross, $pct, $fixed);
    }
}
