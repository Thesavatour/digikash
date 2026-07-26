<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use Database\Factories\RemittanceQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class RemittanceQuote extends Model
{
    /** @use HasFactory<RemittanceQuoteFactory> */
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'user_id',
        'corridor_id',
        'beneficiary_id',
        'source_currency',
        'destination_currency',
        'send_amount',
        'fee_amount',
        'total_pay',
        'exchange_rate',
        'mid_market_rate',
        'receive_amount',
        'payout_method',
        'expires_at',
        'is_consumed',
    ];

    protected function casts(): array
    {
        return [
            'payout_method'   => PayoutMethod::class,
            'send_amount'     => 'float',
            'fee_amount'      => 'float',
            'total_pay'       => 'float',
            'exchange_rate'   => 'float',
            'mid_market_rate' => 'float',
            'receive_amount'  => 'float',
            'expires_at'      => 'datetime',
            'is_consumed'     => 'boolean',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (self $quote): void {
            if (! $quote->quote_id) {
                do {
                    $quote->quote_id = 'QTE'.Str::upper(Str::random(12));
                } while (self::where('quote_id', $quote->quote_id)->exists());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(RemittanceCorridor::class, 'corridor_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(RemittanceTransfer::class, 'quote_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }

    public function isUsable(): bool
    {
        return ! $this->is_consumed && ! $this->isExpired();
    }
}
