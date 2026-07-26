<?php

namespace App\Services\Remittance;

use App\Enums\PayoutMethod;
use App\Models\RemittanceCorridor;
use App\Models\RemittanceQuote;
use App\Models\User;
use App\Services\CurrencyConversionService;
use Exception;
use InvalidArgumentException;

class QuoteService
{
    public function __construct(
        protected CurrencyConversionService $conversionService,
    ) {}

    /**
     * Generate (and persist) a locked remittance quote for the user.
     *
     * @throws Exception
     */
    public function quote(
        User $user,
        RemittanceCorridor $corridor,
        float $sendAmount,
        PayoutMethod $payoutMethod,
        ?int $beneficiaryId = null,
    ): RemittanceQuote {
        $this->assertCorridorAccepts($corridor, $payoutMethod, $sendAmount);

        $midRate    = $this->fetchMidMarketRate($corridor);
        $appliedFx  = $this->applySpread($midRate, $corridor->fx_spread_percent);
        $feeAmount  = $this->calculateFee($corridor, $sendAmount);
        $receiveAmt = round($sendAmount * $appliedFx, 2);

        return RemittanceQuote::create([
            'user_id'              => $user->id,
            'corridor_id'          => $corridor->id,
            'beneficiary_id'       => $beneficiaryId,
            'source_currency'      => $corridor->source_currency,
            'destination_currency' => $corridor->destination_currency,
            'send_amount'          => $sendAmount,
            'fee_amount'           => $feeAmount,
            'total_pay'            => round($sendAmount + $feeAmount, 2),
            'exchange_rate'        => $appliedFx,
            'mid_market_rate'      => $midRate,
            'receive_amount'       => $receiveAmt,
            'payout_method'        => $payoutMethod,
            'expires_at'           => now()->addMinutes($corridor->quote_ttl_minutes),
            'is_consumed'          => false,
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function assertCorridorAccepts(RemittanceCorridor $corridor, PayoutMethod $method, float $amount): void
    {
        if (! $corridor->status) {
            throw new InvalidArgumentException(__('This corridor is currently disabled.'));
        }

        if (! $corridor->supportsPayoutMethod($method)) {
            throw new InvalidArgumentException(__('Selected payout method is not supported on this corridor.'));
        }

        if ($amount < $corridor->min_amount || $amount > $corridor->max_amount) {
            throw new InvalidArgumentException(__('Amount must be between :min and :max.', [
                'min' => $corridor->min_amount,
                'max' => $corridor->max_amount,
            ]));
        }
    }

    /**
     * @throws Exception
     */
    protected function fetchMidMarketRate(RemittanceCorridor $corridor): float
    {
        $converted = $this->conversionService->convertCurrency(
            amount: 1,
            from: $corridor->source_currency,
            to: $corridor->destination_currency,
        );

        if ($converted === null || $converted <= 0) {
            throw new Exception(__('Could not fetch exchange rate for :from to :to.', [
                'from' => $corridor->source_currency,
                'to'   => $corridor->destination_currency,
            ]));
        }

        return (float) $converted;
    }

    protected function applySpread(float $midRate, float $spreadPercent): float
    {
        return round($midRate * (1 - ($spreadPercent / 100)), 8);
    }

    protected function calculateFee(RemittanceCorridor $corridor, float $sendAmount): float
    {
        return round($corridor->fixed_fee + (($sendAmount * $corridor->percent_fee) / 100), 2);
    }
}
