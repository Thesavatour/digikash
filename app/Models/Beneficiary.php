<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use Database\Factories\BeneficiaryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beneficiary extends Model
{
    /** @use HasFactory<BeneficiaryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'nickname',
        'country_code',
        'currency',
        'payout_method',
        'phone_number',
        'email',
        'account_details',
        'relationship',
        'is_favorite',
        'is_verified',
        'last_used_at',
    ];

    protected $appends = ['full_name', 'masked_account'];

    protected function casts(): array
    {
        return [
            'payout_method'   => PayoutMethod::class,
            'account_details' => 'array',
            'is_favorite'     => 'boolean',
            'is_verified'     => 'boolean',
            'last_used_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(RemittanceTransfer::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getMaskedAccountAttribute(): string
    {
        $details = $this->account_details ?? [];
        $value   = $details['account_number']
            ?? $details['phone_number']
            ?? $details['card_number']
            ?? $details['wallet_uuid']
            ?? '';

        if (strlen((string) $value) <= 4) {
            return (string) $value;
        }

        return str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }
}
