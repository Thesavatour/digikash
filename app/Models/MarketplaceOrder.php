<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Marketplace\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'vendor_id',
        'currency_id',
        'gross_amount',
        'commission_amount',
        'vendor_net',
        'status',
        'provider',
        'trx_reference',
        'remarks',
        'released_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status'            => OrderStatus::class,
            'gross_amount'      => 'decimal:8',
            'commission_amount' => 'decimal:8',
            'vendor_net'        => 'decimal:8',
            'released_at'       => 'datetime',
            'refunded_at'       => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
