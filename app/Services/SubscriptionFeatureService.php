<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSubscription;

/**
 * Resolves what a subscriber on a given plan can use, and how much.
 *
 * Subscription plans configure per-plan limits, quotas, access entitlements,
 * and visible benefits. FeatureManager still controls whether a module exists
 * globally; these helpers decide what a subscribed user can do inside it.
 */
class SubscriptionFeatureService
{
    public function __construct(private FeatureManager $featureManager) {}

    /**
     * Check if a user has an active subscription.
     */
    public function hasActiveSubscription(User $user): bool
    {
        return $user->activeSubscription !== null;
    }

    /**
     * Get the user's active subscription with plan and features loaded.
     */
    public function getActiveSubscription(User $user): ?UserSubscription
    {
        return $user->subscriptions()
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->with(['plan.features'])
            ->latest()
            ->first();
    }

    /**
     * Module-aware gate. Combines the global FeatureManager toggle with the
     * subscription-plan entitlement so a feature can only be used when:
     *   1. The platform module itself is enabled in feature_catalog, AND
     *   2. The user's active subscription plan grants the feature.
     *
     * Use this anywhere you would have written a "user has subscription feature"
     * check — middleware, controllers, blade @if, view composers, AJAX guards.
     */
    public function canAccess(User $user, string $featureKey): bool
    {
        if (! $this->featureManager->isEnabled($featureKey)) {
            return false;
        }

        return $this->canUse($user, $featureKey);
    }

    /**
     * Plan-only entitlement check (does NOT consult FeatureManager).
     * Prefer canAccess() for runtime gates; keep this for UI displays
     * where you only want to know "is this feature listed on the plan?".
     *
     * Returns true if:
     * - The user has an active subscription
     * - The plan has this feature with value 'enabled', 'unlimited', or any positive number
     */
    public function canUse(User $user, string $featureKey): bool
    {
        $subscription = $this->getActiveSubscription($user);

        if (! $subscription) {
            return false;
        }

        $feature = $subscription->plan->features
            ->firstWhere('feature_key', $featureKey);

        if (! $feature) {
            return false;
        }

        if ($feature->isAccess()) {
            return $feature->isEnabled();
        }

        if ($feature->isBenefit()) {
            return ! $feature->isDisabled();
        }

        // For limits/quotas, any non-zero value or 'unlimited' grants access
        if ($feature->isUnlimited()) {
            return true;
        }

        return ($feature->numericValue() ?? 0) > 0;
    }

    /**
     * Get the raw limit value for a feature.
     * Returns null for unlimited, 0 if not found, or the integer limit.
     */
    public function getLimit(User $user, string $featureKey): ?int
    {
        $subscription = $this->getActiveSubscription($user);

        if (! $subscription) {
            return 0;
        }

        $feature = $subscription->plan->features
            ->firstWhere('feature_key', $featureKey);

        if (! $feature) {
            return 0;
        }

        if (! $feature->isLimit() && ! $feature->isQuota()) {
            return ! $feature->isDisabled() ? null : 0;
        }

        if ($feature->isUnlimited()) {
            return null; // null means unlimited
        }

        return $feature->numericValue() ?? 0;
    }

    /**
     * Get feature value as a display string (e.g. '5', 'Unlimited', 'Enabled').
     */
    public function getFeatureDisplay(User $user, string $featureKey): string
    {
        $subscription = $this->getActiveSubscription($user);

        if (! $subscription) {
            return __('No Plan');
        }

        $feature = $subscription->plan->features
            ->firstWhere('feature_key', $featureKey);

        if (! $feature) {
            return __('Not included');
        }

        return $feature->displayValue();
    }
}
