<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Data\SplitPaymentData;
use App\Enums\Marketplace\VendorStatus;
use App\Exceptions\NotifyErrorException;
use App\Models\Currency;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\Marketplace\MarketplaceOrderNotification;
use App\Services\Marketplace\Split\SplitProviderFactory;

class MarketplaceOrderHandler
{
    public function __construct(
        protected CommissionCalculator $commission,
        protected SplitProviderFactory $providers,
    ) {}

    /**
     * Validate a marketplace purchase, split the payment, and persist the order.
     *
     * @throws NotifyErrorException
     */
    public function purchase(User $buyer, Vendor $vendor, float $gross, Currency $currency, ?string $remarks = null): MarketplaceOrder
    {
        $settings = MarketplaceSetting::current();

        if (! $settings || ! $settings->enabled) {
            throw new NotifyErrorException(__('The marketplace is not available right now.'));
        }

        if ($vendor->status !== VendorStatus::ACTIVE) {
            throw new NotifyErrorException(__('This vendor is not accepting orders right now.'));
        }

        if ((int) $vendor->user_id === (int) $buyer->id) {
            throw new NotifyErrorException(__('You cannot purchase from your own vendor account.'));
        }

        if ($gross <= 0) {
            throw new NotifyErrorException(__('Invalid amount.'));
        }

        $min = (float) ($settings->min_order_amount ?? 0);
        $max = $settings->max_order_amount !== null ? (float) $settings->max_order_amount : null;

        if ($min > 0 && $gross < $min) {
            throw new NotifyErrorException(__('Amount is below the minimum order amount.'));
        }

        if ($max !== null && $max > 0 && $gross > $max) {
            throw new NotifyErrorException(__('Amount is above the maximum order amount.'));
        }

        $split = $this->commission->forVendor($vendor, $gross);

        if ($split['commission'] > 0 && ! $settings->commission_user_id) {
            throw new NotifyErrorException(__('The marketplace commission account has not been configured. Please contact support.'));
        }

        $data = new SplitPaymentData(
            buyerId: (int) $buyer->id,
            vendorId: (int) $vendor->id,
            currencyId: (int) $currency->id,
            gross: $gross,
            commission: $split['commission'],
            vendorNet: $split['vendor_net'],
            commissionUserId: $settings->commission_user_id ? (int) $settings->commission_user_id : null,
            provider: 'wallet',
            remarks: $remarks,
        );

        $result = $this->providers->make($data->provider)->charge($data);
        $order  = $result->order;

        $this->notify($order, $vendor, $buyer);

        return $order;
    }

    /**
     * Refund a released marketplace order back to the buyer.
     *
     * @throws NotifyErrorException
     */
    public function refund(MarketplaceOrder $order): MarketplaceOrder
    {
        $result = $this->providers->make((string) $order->provider)->refund($order);

        $buyer  = User::find($order->buyer_id);
        $vendor = $order->vendor;

        foreach ([$buyer, $vendor?->user] as $user) {
            $user?->notify(new MarketplaceOrderNotification(
                orderId: (int) $order->id,
                title: __('Order Refunded'),
                message: __('Marketplace order #:id has been refunded.', ['id' => $order->id]),
                data: ['status' => 'refunded'],
            ));
        }

        return $result->order;
    }

    private function notify(MarketplaceOrder $order, Vendor $vendor, User $buyer): void
    {
        $buyer->notify(new MarketplaceOrderNotification(
            orderId: (int) $order->id,
            title: __('Purchase Successful'),
            message: __('Your order #:id was completed successfully.', ['id' => $order->id]),
            data: ['status' => $order->status->value],
        ));

        $vendor->user?->notify(new MarketplaceOrderNotification(
            orderId: (int) $order->id,
            title: __('New Sale'),
            message: __('You received a new order #:id.', ['id' => $order->id]),
            data: ['status' => $order->status->value],
        ));
    }
}
