<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->normalizeLegacyBusinessPlan();

        $this->seedPlan(
            name: 'Starter',
            attributes: [
                'slug'                => 'starter',
                'description'         => 'Browse P2P and use core wallet tools.',
                'trial_days'          => 0,
                'grace_days'          => 3,
                'is_featured'         => false,
                'plan_badge'          => 'FREE',
                'auto_renew_default'  => true,
                'cancellation_policy' => 'end_of_period',
                'sort_order'          => 1,
                'status'              => true,
            ],
            basePrice: 0,
            halfYearlyDiscount: null,
            yearlyDiscount: null,
            features: [
                ['feature_key' => 'daily_transaction_limit', 'feature_label' => 'Daily Transactions', 'feature_value' => '5', 'feature_type' => 'quota', 'reset_cycle' => 'daily', 'sort_order' => 1],
                ['feature_key' => 'monthly_withdraw_limit', 'feature_label' => 'Monthly Withdrawal', 'feature_value' => '$500', 'feature_type' => 'limit', 'reset_cycle' => 'monthly', 'sort_order' => 2],
                ['feature_key' => 'wallet_balance_cap', 'feature_label' => 'Wallet Balance', 'feature_value' => '$1,000', 'feature_type' => 'limit', 'sort_order' => 3],
                ['feature_key' => 'p2p_marketplace_browse', 'feature_label' => 'P2P Marketplace Browsing', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 4],
                ['feature_key' => 'p2p_trading', 'feature_label' => 'P2P Trading', 'feature_value' => 'disabled', 'feature_type' => 'access', 'sort_order' => 5],
                ['feature_key' => 'p2p_ad_limit', 'feature_label' => 'P2P Ads', 'feature_value' => '0', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 6],
                ['feature_key' => 'p2p_trade_limit', 'feature_label' => 'P2P Trades', 'feature_value' => '0', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 7],
                ['feature_key' => 'virtual_cards', 'feature_label' => 'Virtual Cards', 'feature_value' => 'disabled', 'feature_type' => 'access', 'sort_order' => 8],
                ['feature_key' => 'wallet_earn', 'feature_label' => 'Wallet Earn / Staking', 'feature_value' => 'disabled', 'feature_type' => 'access', 'sort_order' => 9],
                ['feature_key' => 'withdrawal_speed', 'feature_label' => 'Withdrawal Speed', 'feature_value' => 'Standard processing', 'feature_type' => 'benefit', 'sort_order' => 10],
                ['feature_key' => 'support_priority', 'feature_label' => 'Support', 'feature_value' => 'Standard support', 'feature_type' => 'benefit', 'sort_order' => 11],
            ],
        );

        $this->seedPlan(
            name: 'Pro',
            attributes: [
                'slug'                => 'pro',
                'description'         => 'P2P trading, cards, and priority support.',
                'trial_days'          => 7,
                'grace_days'          => 5,
                'is_featured'         => true,
                'plan_badge'          => 'MOST POPULAR',
                'auto_renew_default'  => true,
                'cancellation_policy' => 'end_of_period',
                'sort_order'          => 2,
                'status'              => true,
            ],
            basePrice: 9.99,
            halfYearlyDiscount: 10,
            yearlyDiscount: 20,
            features: [
                ['feature_key' => 'daily_transaction_limit', 'feature_label' => 'Daily Transactions', 'feature_value' => '50', 'feature_type' => 'quota', 'reset_cycle' => 'daily', 'sort_order' => 1],
                ['feature_key' => 'monthly_transaction_limit', 'feature_label' => 'Monthly Transactions', 'feature_value' => '500', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 2],
                ['feature_key' => 'monthly_withdraw_limit', 'feature_label' => 'Monthly Withdrawal', 'feature_value' => '$10,000', 'feature_type' => 'limit', 'reset_cycle' => 'monthly', 'sort_order' => 3],
                ['feature_key' => 'monthly_send_limit', 'feature_label' => 'Monthly Send Money', 'feature_value' => '$25,000', 'feature_type' => 'limit', 'reset_cycle' => 'monthly', 'sort_order' => 4],
                ['feature_key' => 'wallet_balance_cap', 'feature_label' => 'Wallet Balance', 'feature_value' => '$100,000', 'feature_type' => 'limit', 'sort_order' => 5],
                ['feature_key' => 'p2p_marketplace_browse', 'feature_label' => 'P2P Marketplace Browsing', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 6],
                ['feature_key' => 'p2p_trading', 'feature_label' => 'P2P Trading', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 7],
                ['feature_key' => 'p2p_ad_limit', 'feature_label' => 'P2P Ads', 'feature_value' => '10', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 8],
                ['feature_key' => 'p2p_trade_limit', 'feature_label' => 'P2P Trades', 'feature_value' => '50', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 9],
                ['feature_key' => 'virtual_cards', 'feature_label' => 'Virtual Cards', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 10],
                ['feature_key' => 'virtual_card_limit', 'feature_label' => 'Virtual Card Slots', 'feature_value' => '3', 'feature_type' => 'quota', 'sort_order' => 11],
                ['feature_key' => 'wallet_earn', 'feature_label' => 'Wallet Earn / Staking', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 12],
                ['feature_key' => 'withdrawal_speed', 'feature_label' => 'Withdrawal Speed', 'feature_value' => 'Priority withdrawals', 'feature_type' => 'benefit', 'sort_order' => 13],
                ['feature_key' => 'support_priority', 'feature_label' => 'Support', 'feature_value' => 'Priority support', 'feature_type' => 'benefit', 'sort_order' => 14],
            ],
        );

        $this->seedPlan(
            name: 'Business',
            attributes: [
                'slug'                => 'business',
                'description'         => 'Higher limits and dedicated support.',
                'trial_days'          => 14,
                'grace_days'          => 7,
                'is_featured'         => false,
                'plan_badge'          => 'BEST VALUE',
                'auto_renew_default'  => true,
                'cancellation_policy' => 'end_of_period',
                'sort_order'          => 3,
                'status'              => true,
            ],
            basePrice: 29.99,
            halfYearlyDiscount: 15,
            yearlyDiscount: 25,
            features: [
                ['feature_key' => 'daily_transaction_limit', 'feature_label' => 'Daily Transactions', 'feature_value' => '500', 'feature_type' => 'quota', 'reset_cycle' => 'daily', 'sort_order' => 1],
                ['feature_key' => 'monthly_transaction_limit', 'feature_label' => 'Monthly Transactions', 'feature_value' => '5,000', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 2],
                ['feature_key' => 'monthly_withdraw_limit', 'feature_label' => 'Monthly Withdrawal', 'feature_value' => '$100,000', 'feature_type' => 'limit', 'reset_cycle' => 'monthly', 'sort_order' => 3],
                ['feature_key' => 'monthly_send_limit', 'feature_label' => 'Monthly Send Money', 'feature_value' => '$250,000', 'feature_type' => 'limit', 'reset_cycle' => 'monthly', 'sort_order' => 4],
                ['feature_key' => 'wallet_balance_cap', 'feature_label' => 'Wallet Balance', 'feature_value' => '$1,000,000', 'feature_type' => 'limit', 'sort_order' => 5],
                ['feature_key' => 'p2p_marketplace_browse', 'feature_label' => 'P2P Marketplace Browsing', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 6],
                ['feature_key' => 'p2p_trading', 'feature_label' => 'P2P Trading', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 7],
                ['feature_key' => 'p2p_ad_limit', 'feature_label' => 'P2P Ads', 'feature_value' => 'unlimited', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 8],
                ['feature_key' => 'p2p_trade_limit', 'feature_label' => 'P2P Trades', 'feature_value' => 'unlimited', 'feature_type' => 'quota', 'reset_cycle' => 'monthly', 'sort_order' => 9],
                ['feature_key' => 'virtual_cards', 'feature_label' => 'Virtual Cards', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 10],
                ['feature_key' => 'virtual_card_limit', 'feature_label' => 'Virtual Card Slots', 'feature_value' => '20', 'feature_type' => 'quota', 'sort_order' => 11],
                ['feature_key' => 'wallet_earn', 'feature_label' => 'Wallet Earn / Staking', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 12],
                ['feature_key' => 'withdrawal_speed', 'feature_label' => 'Withdrawal Speed', 'feature_value' => 'Fast-track withdrawals', 'feature_type' => 'benefit', 'sort_order' => 13],
                ['feature_key' => 'support_priority', 'feature_label' => 'Support', 'feature_value' => 'Dedicated support', 'feature_type' => 'benefit', 'sort_order' => 14],
                ['feature_key' => 'api_access', 'feature_label' => 'API Access', 'feature_value' => 'enabled', 'feature_type' => 'access', 'sort_order' => 15],
            ],
        );
    }

    /**
     * @param array<string, mixed>             $attributes
     * @param array<int, array<string, mixed>> $features
     */
    private function seedPlan(string $name, array $attributes, float $basePrice, ?int $halfYearlyDiscount, ?int $yearlyDiscount, array $features): void
    {
        $plan = SubscriptionPlan::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            array_merge($attributes, ['name' => $name])
        );

        $plan->prices()->delete();

        $halfPrice   = $basePrice * 6;
        $yearlyPrice = $basePrice * 12;

        if ($halfYearlyDiscount !== null) {
            $halfPrice = round($halfPrice * (1 - $halfYearlyDiscount / 100), 2);
        }

        if ($yearlyDiscount !== null) {
            $yearlyPrice = round($yearlyPrice * (1 - $yearlyDiscount / 100), 2);
        }

        $plan->prices()->createMany([
            ['billing_cycle' => BillingCycle::Monthly->value, 'price' => $basePrice, 'discount' => null],
            ['billing_cycle' => BillingCycle::HalfYearly->value, 'price' => round($halfPrice, 2), 'discount' => $halfYearlyDiscount],
            ['billing_cycle' => BillingCycle::Yearly->value, 'price' => round($yearlyPrice, 2), 'discount' => $yearlyDiscount],
        ]);

        $plan->features()->delete();

        foreach ($features as $feature) {
            $plan->features()->create($feature);
        }
    }

    private function normalizeLegacyBusinessPlan(): void
    {
        $businessExists = SubscriptionPlan::query()
            ->where('slug', 'business')
            ->exists();

        if ($businessExists) {
            SubscriptionPlan::query()
                ->where('slug', 'enterprise')
                ->update([
                    'status'     => false,
                    'sort_order' => 99,
                ]);

            return;
        }

        SubscriptionPlan::query()
            ->where('slug', 'enterprise')
            ->update([
                'name'        => 'Business',
                'slug'        => 'business',
                'description' => 'Higher limits and dedicated support.',
            ]);
    }
}
