<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MarketplaceSetting extends Model
{
    protected $table = 'marketplace_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled'             => 'boolean',
            'commission_pct'      => 'decimal:4',
            'commission_fixed'    => 'decimal:8',
            'min_order_amount'    => 'decimal:8',
            'max_order_amount'    => 'decimal:8',
            'auto_release'        => 'boolean',
            'payout_hold_minutes' => 'integer',
            'commission_user_id'  => 'integer',
        ];
    }

    public function commissionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commission_user_id');
    }

    public static function current(): ?self
    {
        if (! Schema::hasTable('marketplace_settings')) {
            return null;
        }

        return Cache::rememberForever('marketplace_settings.current', function () {
            return self::query()->first();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget('marketplace_settings.current');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }
}
