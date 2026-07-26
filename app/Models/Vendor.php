<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Marketplace\VendorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'status',
        'commission_pct_override',
        'about',
        'stripe_connect_account_id',
        'stripe_onboarding_status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status'                  => VendorStatus::class,
            'commission_pct_override' => 'decimal:4',
            'approved_at'             => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === VendorStatus::ACTIVE;
    }
}
