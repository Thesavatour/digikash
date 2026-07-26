<?php

namespace App\Http\Resources;

use App\Models\RemittanceQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RemittanceQuote
 */
class RemittanceQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quote_id'             => $this->quote_id,
            'corridor_id'          => $this->corridor_id,
            'source_currency'      => $this->source_currency,
            'destination_currency' => $this->destination_currency,
            'send_amount'          => (float) $this->send_amount,
            'fee_amount'           => (float) $this->fee_amount,
            'total_pay'            => (float) $this->total_pay,
            'exchange_rate'        => (float) $this->exchange_rate,
            'mid_market_rate'      => $this->mid_market_rate ? (float) $this->mid_market_rate : null,
            'receive_amount'       => (float) $this->receive_amount,
            'payout_method'        => $this->payout_method?->value,
            'expires_at'           => $this->expires_at?->toIso8601String(),
            'expires_in_seconds'   => $this->expires_at ? max(0, now()->diffInSeconds($this->expires_at, false)) : 0,
            'is_consumed'          => (bool) $this->is_consumed,
        ];
    }
}
