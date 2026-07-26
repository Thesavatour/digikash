<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeatureAccessRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int        $id
 * @property int        $feature_id
 * @property string     $panel
 * @property bool       $is_visible
 * @property bool       $is_accessible
 * @property array|null $conditions
 * @property Feature    $feature
 */
class FeatureAccessRule extends Model
{
    /** @use HasFactory<FeatureAccessRuleFactory> */
    use HasFactory;

    public const PANEL_USER = 'user';

    public const PANEL_MERCHANT = 'merchant';

    public const PANEL_AGENT = 'agent';

    public const PANELS = [
        self::PANEL_USER,
        self::PANEL_MERCHANT,
        self::PANEL_AGENT,
    ];

    public const IDENTIFIER_WALLET_ID = 'wallet_id';

    public const IDENTIFIER_EMAIL = 'email';

    public const IDENTIFIER_MOBILE = 'mobile';

    public const IDENTIFIER_METHODS = [
        self::IDENTIFIER_WALLET_ID,
        self::IDENTIFIER_EMAIL,
        self::IDENTIFIER_MOBILE,
    ];

    protected $fillable = [
        'feature_id',
        'panel',
        'is_visible',
        'is_accessible',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'is_visible'    => 'boolean',
            'is_accessible' => 'boolean',
            'conditions'    => 'array',
        ];
    }

    /**
     * @return BelongsTo<Feature, FeatureAccessRule>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    public function requiresKyc(): bool
    {
        return (bool) data_get($this->conditions, 'requires_kyc', false);
    }

    public function requiresPhone(): bool
    {
        return (bool) data_get($this->conditions, 'requires_phone', false);
    }

    /**
     * @return array<int, string>
     */
    public function allowedCountries(): array
    {
        $countries = (array) data_get($this->conditions, 'countries_allowed', []);

        return array_values(array_filter(array_map('strtoupper', $countries)));
    }

    /**
     * Whether a given recipient identifier method (wallet_id / email / mobile) is allowed.
     */
    public function isIdentifierAllowed(string $method): bool
    {
        return in_array($method, $this->allowedIdentifierMethods(), true);
    }

    /**
     * List of recipient identifier methods allowed for this feature/panel rule.
     *
     * @return array<int, string>
     */
    public function allowedIdentifierMethods(): array
    {
        $methods = (array) data_get($this->conditions, 'identifier_methods', []);

        $allowed = [];

        foreach (self::IDENTIFIER_METHODS as $method) {
            if ((bool) data_get($methods, $method, false)) {
                $allowed[] = $method;
            }
        }

        return $allowed;
    }

    public function requiresVerifiedEmail(): bool
    {
        return (bool) data_get($this->conditions, 'require_verified_email', false);
    }

    public function requiresVerifiedMobile(): bool
    {
        return (bool) data_get($this->conditions, 'require_verified_mobile', false);
    }
}
