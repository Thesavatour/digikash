<?php

declare(strict_types=1);

namespace App\Services\Marketplace\Split;

use App\Data\SplitPaymentData;
use App\Models\MarketplaceOrder;

/**
 * Strategy contract for routing a marketplace payment so the vendor is paid and
 * the platform retains a commission. The internal `wallet` driver settles
 * instantly between Digikash wallets; Phase 2 gateway drivers (Stripe Connect,
 * Mercado Pago, PayPal Marketplace) implement the same contract.
 */
interface SplitProvider
{
    /** Machine code for this provider, e.g. "wallet" or "stripe_connect". */
    public function code(): string;

    /** Whether the split settles in a single synchronous call (no off-site redirect). */
    public function supportsInstantSplit(): bool;

    /** Execute the split: persist the order, move the money, record the ledger. */
    public function charge(SplitPaymentData $data): SplitResult;

    /** Reverse a previously settled order back to the buyer. */
    public function refund(MarketplaceOrder $order): SplitResult;
}
