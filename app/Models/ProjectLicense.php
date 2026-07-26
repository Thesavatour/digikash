<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProjectLicense extends Model
{
    protected $fillable = [
        'product_slug',
        'item_id',
        'purchase_code',
        'license_token',
        'buyer_username',
        'domain',
        'status',
        'support_until',
        'activated_at',
        'last_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'purchase_code'   => 'encrypted',
            'support_until'   => 'datetime',
            'activated_at'    => 'datetime',
            'last_checked_at' => 'datetime',
            'metadata'        => 'array',
        ];
    }

    /**
     * The license that governs THIS installation.
     *
     * Activation keys a license row on (product_slug + domain), so the
     * displayed license must be resolved the same way — otherwise, when more
     * than one license row exists for the product (e.g. an old activation on a
     * different host, or a re-activation with a new key), the UI would show a
     * stale row (the highest id) instead of the one just activated for this
     * domain. We therefore prefer the row bound to the current host, and only
     * fall back to the latest row for the product when none is bound here
     * (e.g. a portal-created license with a blank domain).
     */
    public static function current(): ?self
    {
        $slug   = config('project_updater.product_slug');
        $domain = request()->getHost();

        return self::query()
            ->where('product_slug', $slug)
            ->where('domain', $domain)
            ->latest('id')
            ->first()
            ?? self::query()
                ->where('product_slug', $slug)
                ->latest('id')
                ->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Safely decrypt the stored purchase code, returning null when the value
     * is missing or cannot be decrypted (e.g. APP_KEY rotated since storage).
     */
    public function safePurchaseCode(): ?string
    {
        $raw = $this->getRawOriginal('purchase_code');

        if (blank($raw)) {
            return null;
        }

        try {
            $value = $this->purchase_code;
        } catch (\Throwable) {
            return null;
        }

        return blank($value) ? null : (string) $value;
    }

    public function hasUsablePurchaseCode(): bool
    {
        return filled($this->safePurchaseCode());
    }

    public function supportStatusLabel(): string
    {
        if (! $this->support_until instanceof Carbon) {
            return __('Unknown');
        }

        if ($this->support_until->isPast()) {
            return __('Support expired');
        }

        if ($this->supportExpiresSoon()) {
            return __('Support ending soon');
        }

        return __('Supported');
    }

    public function supportTone(): string
    {
        if (! $this->support_until instanceof Carbon) {
            return 'secondary';
        }

        if ($this->support_until->isPast()) {
            return 'danger';
        }

        if ($this->supportExpiresSoon()) {
            return 'warning';
        }

        return 'success';
    }

    public function supportDaysRemaining(): ?int
    {
        if (! $this->support_until instanceof Carbon) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->support_until->copy()->startOfDay(), false);
    }

    public function supportExpiresSoon(int $days = 30): bool
    {
        $daysRemaining = $this->supportDaysRemaining();

        return $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= $days;
    }
}
