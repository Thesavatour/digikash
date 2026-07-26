<?php

declare(strict_types=1);

namespace App\Services\Marketplace\Split;

use Illuminate\Support\Facades\App;
use InvalidArgumentException;

class SplitProviderFactory
{
    /**
     * Resolve a split provider by its machine code. The internal `wallet`
     * driver ships in Phase 1; `stripe_connect` and other gateway drivers are
     * reserved for Phase 2.
     */
    public function make(string $code): SplitProvider
    {
        return match ($code) {
            'wallet' => App::make(WalletSplitProvider::class),

            default => throw new InvalidArgumentException(sprintf('Unsupported split provider: %s', $code)),
        };
    }
}
