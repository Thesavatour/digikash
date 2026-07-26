<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'flag',
        'name',
        'code',
        'is_default',
        'is_rtl',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_rtl'     => 'boolean',
        'status'     => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model and listen for model events.
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    /**
     * Get the default language (cached).
     */
    public static function default(): ?self
    {
        if (! Schema::hasTable('languages')) {
            return null;
        }

        return Cache::rememberForever('default_language', function () {
            return self::where('is_default', true)->first(['code', 'is_rtl']);
        });
    }

    /**
     * Resolve the text direction ("ltr" or "rtl") for the active locale.
     */
    public static function currentDirection(): string
    {
        return self::directionForLocale(app()->getLocale());
    }

    /**
     * Resolve the text direction ("ltr" or "rtl") for a given language code.
     */
    public static function directionForLocale(string $code): string
    {
        return in_array($code, self::rtlCodes(), true) ? 'rtl' : 'ltr';
    }

    /**
     * Get the codes of every right-to-left language (cached).
     *
     * @return array<int, string>
     */
    public static function rtlCodes(): array
    {
        if (! Schema::hasTable('languages')) {
            return [];
        }

        return Cache::rememberForever('languageRtlCodes', function () {
            return self::where('is_rtl', true)->pluck('code')->all();
        });
    }

    /**
     * Get all active languages with name and code only (cached).
     */
    public static function languageGet(): Collection
    {
        if (! Schema::hasTable('languages')) {
            return collect();
        }

        return Cache::rememberForever('languagesPluck', function () {
            return self::where('status', Status::ACTIVE)->pluck('name', 'code');
        });
    }

    /**
     * Get all active languages with full fields (cached).
     */
    public static function activeCached(): Collection
    {
        if (! Schema::hasTable('languages')) {
            return collect();
        }

        return Cache::rememberForever('languages', function () {
            return self::where('status', true)->get();
        });
    }

    /**
     * Clear all cached language data.
     */
    public static function flushCache(): void
    {
        Cache::forget('default_language');
        Cache::forget('languagesPluck');
        Cache::forget('languages');
        Cache::forget('languageRtlCodes');
    }
}
