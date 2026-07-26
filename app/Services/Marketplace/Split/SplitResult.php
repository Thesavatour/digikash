<?php

declare(strict_types=1);

namespace App\Services\Marketplace\Split;

use App\Models\MarketplaceOrder;

/**
 * Outcome of a split provider operation. `redirectUrl` is reserved for Phase 2
 * gateway providers (e.g. Stripe Connect Checkout) that require an off-site
 * redirect; the internal wallet provider settles instantly and leaves it null.
 */
class SplitResult
{
    public function __construct(
        public MarketplaceOrder $order,
        public bool $success = true,
        public ?string $redirectUrl = null,
        public ?string $message = null,
    ) {}
}
