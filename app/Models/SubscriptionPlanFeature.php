<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanFeature extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'feature_key',
        'feature_label',
        'feature_value',
        'feature_type',
        'reset_cycle',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isUnlimited(): bool
    {
        return strtolower((string) $this->feature_value) === 'unlimited';
    }

    public function isEnabled(): bool
    {
        return in_array(strtolower((string) $this->feature_value), ['enabled', '1', 'true', 'yes'], true);
    }

    public function isDisabled(): bool
    {
        return in_array(strtolower((string) $this->feature_value), ['disabled', '0', 'false', 'no', 'not included'], true);
    }

    public function isToggle(): bool
    {
        return $this->feature_type === 'toggle';
    }

    public function isLimit(): bool
    {
        return $this->feature_type === 'limit';
    }

    public function isQuota(): bool
    {
        return $this->feature_type === 'quota';
    }

    public function isAccess(): bool
    {
        return in_array($this->feature_type, ['access', 'toggle'], true);
    }

    public function isBenefit(): bool
    {
        return $this->feature_type === 'benefit';
    }

    public function numericValue(): ?int
    {
        if ($this->isUnlimited()) {
            return null;
        }

        if (is_numeric($this->feature_value)) {
            return (int) $this->feature_value;
        }

        $cleaned = preg_replace('/[^0-9.]/', '', (string) $this->feature_value);

        return $cleaned !== '' && $cleaned !== '.' ? (int) $cleaned : null;
    }

    public function displayValue(): string
    {
        if ($this->isUnlimited()) {
            return __('Unlimited');
        }

        if ($this->isAccess()) {
            return $this->isEnabled() ? __('Included') : __('Not included');
        }

        return (string) $this->feature_value;
    }
}
