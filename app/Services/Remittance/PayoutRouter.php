<?php

namespace App\Services\Remittance;

use App\Enums\PayoutMethod;
use App\Models\RemittanceCorridor;
use Exception;

class PayoutRouter
{
    /**
     * Find the best active corridor for the given source/destination pair
     * that supports the requested payout method.
     *
     * @throws Exception
     */
    public function resolveCorridor(string $sourceCurrency, string $destinationCurrency, PayoutMethod $payoutMethod): RemittanceCorridor
    {
        $corridor = RemittanceCorridor::query()
            ->active()
            ->forPair($sourceCurrency, $destinationCurrency)
            ->get()
            ->first(fn (RemittanceCorridor $corridor): bool => $corridor->supportsPayoutMethod($payoutMethod));

        if (! $corridor) {
            throw new Exception(__('No active corridor found for :from → :to with :method.', [
                'from'   => strtoupper($sourceCurrency),
                'to'     => strtoupper($destinationCurrency),
                'method' => $payoutMethod->label(),
            ]));
        }

        return $corridor;
    }

    /**
     * Hint a corridor by id (used after the user picks a quote).
     *
     * @throws Exception
     */
    public function findActiveById(int $corridorId): RemittanceCorridor
    {
        $corridor = RemittanceCorridor::active()->find($corridorId);

        if (! $corridor) {
            throw new Exception(__('Selected corridor is no longer active.'));
        }

        return $corridor;
    }
}
