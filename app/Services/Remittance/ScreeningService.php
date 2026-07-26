<?php

namespace App\Services\Remittance;

use App\Models\Beneficiary;
use App\Models\User;

/**
 * Lightweight AML / sanctions screening. Real deployments should plug in
 * a third-party provider (ComplyAdvantage, Sanctions.io, etc.) inside the
 * `screen()` method. The default implementation enforces only the basics
 * so the rest of the pipeline can rely on a consistent return shape.
 */
class ScreeningService
{
    /**
     * Hard-coded high-risk country list. Replace with a config-driven list
     * or live OFAC/UN feed in production.
     *
     * @var array<int, string>
     */
    protected array $blockedCountries = ['KP', 'IR', 'SY'];

    /**
     * Per-tier single-transaction caps in source currency. tier 0 = no KYC
     * required, low cap. Higher tiers progressively raise the cap. Real
     * deployments should drive this from a settings table or compliance
     * provider; the constants below keep the contract testable.
     *
     * @var array<int, float>
     */
    protected array $tierTransactionCap = [
        0 => 1000,
        1 => 5000,
        2 => 10000,
        3 => 25000,
    ];

    /**
     * @return array{passed: bool, reason: ?string, risk_score: int, checked_at: string, required_tier: int}
     */
    public function screen(
        User $sender,
        Beneficiary $beneficiary,
        float $sendAmountSourceCurrency,
        int $requiredKycTier = 0,
    ): array {
        $reasons = [];
        $score   = 0;

        if (in_array(strtoupper($beneficiary->country_code), $this->blockedCountries, true)) {
            $reasons[] = __('Beneficiary country is on the restricted list.');
            $score += 100;
        }

        // Treat KYC verification as "tier 1". A corridor requiring tier >= 1
        // must have a verified sender; tier 0 corridors skip KYC entirely.
        if ($requiredKycTier >= 1 && ! $sender->isKycVerified()) {
            $reasons[] = __('Sender KYC is not approved for this corridor (requires tier :tier).', ['tier' => $requiredKycTier]);
            $score += 80;
        }

        $cap = $this->tierTransactionCap[$requiredKycTier] ?? $this->tierTransactionCap[0];

        if ($sendAmountSourceCurrency > $cap) {
            $reasons[] = __('Amount exceeds the :cap limit for tier :tier.', [
                'cap'  => number_format($cap, 2),
                'tier' => $requiredKycTier,
            ]);
            $score += 60;
        }

        return [
            'passed'        => $reasons === [] && $score < 80,
            'reason'        => $reasons === [] ? null : implode(' ', $reasons),
            'risk_score'    => $score,
            'required_tier' => $requiredKycTier,
            'checked_at'    => now()->toIso8601String(),
        ];
    }
}
