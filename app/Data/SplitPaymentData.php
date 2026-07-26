<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Immutable description of an intended marketplace split payment. Built by the
 * order handler from validated checkout input and consumed by a SplitProvider.
 */
class SplitPaymentData
{
    public function __construct(
        public int $buyerId,
        public int $vendorId,
        public int $currencyId,
        public float $gross,
        public float $commission,
        public float $vendorNet,
        public ?int $commissionUserId = null,
        public string $provider = 'wallet',
        public ?string $remarks = null,
    ) {}
}
