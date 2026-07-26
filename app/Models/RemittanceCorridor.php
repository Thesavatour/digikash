<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use Database\Factories\RemittanceCorridorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemittanceCorridor extends Model
{
    /** @use HasFactory<RemittanceCorridorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'source_country',
        'source_currency',
        'destination_country',
        'destination_currency',
        'allowed_payout_methods',
        'payout_gateway',
        'fx_spread_percent',
        'fixed_fee',
        'percent_fee',
        'min_amount',
        'max_amount',
        'quote_ttl_minutes',
        'required_kyc_tier',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'allowed_payout_methods' => 'array',
            'fx_spread_percent'      => 'float',
            'fixed_fee'              => 'float',
            'percent_fee'            => 'float',
            'min_amount'             => 'float',
            'max_amount'             => 'float',
            'quote_ttl_minutes'      => 'integer',
            'required_kyc_tier'      => 'integer',
            'status'                 => 'boolean',
        ];
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(RemittanceQuote::class, 'corridor_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(RemittanceTransfer::class, 'corridor_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeForPair(Builder $query, string $sourceCurrency, string $destinationCurrency): Builder
    {
        return $query
            ->where('source_currency', strtoupper($sourceCurrency))
            ->where('destination_currency', strtoupper($destinationCurrency));
    }

    /**
     * @return array<int, PayoutMethod>
     */
    public function payoutMethodEnums(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?PayoutMethod => PayoutMethod::tryFrom($value),
            (array) $this->allowed_payout_methods,
        )));
    }

    public function supportsPayoutMethod(PayoutMethod $method): bool
    {
        return in_array($method->value, (array) $this->allowed_payout_methods, true);
    }
}
