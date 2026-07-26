<?php

declare(strict_types=1);

namespace App\Services\Marketplace\Split;

use App\Data\SplitPaymentData;
use App\Data\TransactionData;
use App\Enums\AmountFlow;
use App\Enums\Marketplace\OrderStatus;
use App\Enums\MethodType;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Exceptions\NotifyErrorException;
use App\Models\Currency;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Services\TransactionService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 split driver: settles a marketplace payment entirely between internal
 * Digikash wallets. The buyer is debited the gross amount; the vendor's wallet
 * receives the net; the platform commission account receives the commission.
 * Money is always conserved: gross == vendor_net + commission.
 */
class WalletSplitProvider implements SplitProvider
{
    public function __construct(
        protected WalletService $wallets,
        protected TransactionService $transactions,
    ) {}

    public function code(): string
    {
        return 'wallet';
    }

    public function supportsInstantSplit(): bool
    {
        return true;
    }

    public function charge(SplitPaymentData $data): SplitResult
    {
        return DB::transaction(function () use ($data) {
            $vendor   = Vendor::query()->findOrFail($data->vendorId);
            $currency = Currency::query()->findOrFail($data->currencyId);
            $code     = (string) $currency->code;

            $buyerWallet = $this->lockedWallet($data->buyerId, $data->currencyId);

            if (! $buyerWallet) {
                throw new NotifyErrorException(__('Your :code wallet was not found.', ['code' => $code]));
            }

            $debited = Wallet::query()
                ->whereKey($buyerWallet->id)
                ->where('balance', '>=', $data->gross)
                ->decrement('balance', $data->gross);

            if ($debited !== 1) {
                throw new NotifyErrorException(__('Insufficient balance to complete this purchase.'));
            }

            $vendorWallet = $this->lockedWallet((int) $vendor->user_id, $data->currencyId, create: true);

            if ($data->vendorNet > 0) {
                $this->wallets->addMoney($vendorWallet, $data->vendorNet);
            }

            $commissionWallet = null;
            if ($data->commissionUserId && $data->commission > 0) {
                $commissionWallet = $this->lockedWallet($data->commissionUserId, $data->currencyId, create: true);
                $this->wallets->addMoney($commissionWallet, $data->commission);
            }

            $order = MarketplaceOrder::create([
                'buyer_id'          => $data->buyerId,
                'vendor_id'         => $vendor->id,
                'currency_id'       => $data->currencyId,
                'gross_amount'      => $data->gross,
                'commission_amount' => $data->commission,
                'vendor_net'        => $data->vendorNet,
                'status'            => OrderStatus::RELEASED,
                'provider'          => $this->code(),
                'remarks'           => $data->remarks,
                'released_at'       => now(),
            ]);

            $reference = 'marketplace_order_'.$order->id;
            $order->update(['trx_reference' => $reference]);

            $this->recordTransaction(
                userId: $data->buyerId,
                type: TrxType::MARKETPLACE_SALE,
                amount: $data->gross,
                flow: AmountFlow::MINUS,
                currency: $code,
                walletUuid: $buyerWallet->uuid,
                reference: $reference,
                remarks: __('Marketplace purchase from :vendor (Order #:id).', ['vendor' => $vendor->display_name, 'id' => $order->id]),
                description: 'marketplace sale',
                trxData: ['order_id' => $order->id, 'vendor_id' => $vendor->id, 'commission' => $data->commission],
            );

            if ($data->vendorNet > 0) {
                $this->recordTransaction(
                    userId: (int) $vendor->user_id,
                    type: TrxType::MARKETPLACE_PAYOUT,
                    amount: $data->vendorNet,
                    flow: AmountFlow::PLUS,
                    currency: $code,
                    walletUuid: $vendorWallet->uuid,
                    reference: $reference,
                    remarks: __('Marketplace payout for Order #:id.', ['id' => $order->id]),
                    description: 'marketplace payout',
                    trxData: ['order_id' => $order->id, 'gross' => $data->gross, 'commission' => $data->commission],
                );
            }

            if ($commissionWallet && $data->commission > 0) {
                $this->recordTransaction(
                    userId: (int) $data->commissionUserId,
                    type: TrxType::MARKETPLACE_COMMISSION,
                    amount: $data->commission,
                    flow: AmountFlow::PLUS,
                    currency: $code,
                    walletUuid: $commissionWallet->uuid,
                    reference: $reference,
                    remarks: __('Marketplace commission for Order #:id.', ['id' => $order->id]),
                    description: 'marketplace commission',
                    trxData: ['order_id' => $order->id, 'vendor_id' => $vendor->id],
                );
            }

            return new SplitResult(order: $order->refresh());
        }, 3);
    }

    public function refund(MarketplaceOrder $order): SplitResult
    {
        return DB::transaction(function () use ($order) {
            $order = MarketplaceOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::RELEASED) {
                throw new NotifyErrorException(__('Only released orders can be refunded.'));
            }

            $vendor   = Vendor::query()->findOrFail($order->vendor_id);
            $currency = Currency::query()->findOrFail($order->currency_id);
            $code     = (string) $currency->code;

            $gross      = $this->toMoney($order->gross_amount);
            $vendorNet  = $this->toMoney($order->vendor_net);
            $commission = $this->toMoney($order->commission_amount);

            if ($vendorNet > 0) {
                $vendorWallet = $this->lockedWallet((int) $vendor->user_id, (int) $order->currency_id);
                $clawed       = Wallet::query()
                    ->whereKey($vendorWallet?->id)
                    ->where('balance', '>=', $vendorNet)
                    ->decrement('balance', $vendorNet);

                if ($clawed !== 1) {
                    throw new NotifyErrorException(__('The vendor balance is too low to refund this order.'));
                }

                $this->recordTransaction(
                    userId: (int) $vendor->user_id,
                    type: TrxType::MARKETPLACE_REFUND,
                    amount: $vendorNet,
                    flow: AmountFlow::MINUS,
                    currency: $code,
                    walletUuid: $vendorWallet->uuid,
                    reference: (string) $order->trx_reference,
                    remarks: __('Marketplace refund reversal for Order #:id.', ['id' => $order->id]),
                    description: 'marketplace refund',
                    trxData: ['order_id' => $order->id, 'refund' => true],
                );
            }

            if ($commission > 0 && $order->trx_reference) {
                $commissionWallet = $this->commissionWalletForOrder((int) $order->currency_id);
                if ($commissionWallet) {
                    $clawed = Wallet::query()
                        ->whereKey($commissionWallet->id)
                        ->where('balance', '>=', $commission)
                        ->decrement('balance', $commission);

                    if ($clawed === 1) {
                        $this->recordTransaction(
                            userId: (int) $commissionWallet->user_id,
                            type: TrxType::MARKETPLACE_REFUND,
                            amount: $commission,
                            flow: AmountFlow::MINUS,
                            currency: $code,
                            walletUuid: $commissionWallet->uuid,
                            reference: (string) $order->trx_reference,
                            remarks: __('Marketplace commission reversal for Order #:id.', ['id' => $order->id]),
                            description: 'marketplace refund',
                            trxData: ['order_id' => $order->id, 'refund' => true, 'commission' => true],
                        );
                    }
                }
            }

            $buyerWallet = $this->lockedWallet((int) $order->buyer_id, (int) $order->currency_id, create: true);
            $this->wallets->addMoney($buyerWallet, $gross);

            $this->recordTransaction(
                userId: (int) $order->buyer_id,
                type: TrxType::MARKETPLACE_REFUND,
                amount: $gross,
                flow: AmountFlow::PLUS,
                currency: $code,
                walletUuid: $buyerWallet->uuid,
                reference: (string) $order->trx_reference,
                remarks: __('Marketplace refund for Order #:id.', ['id' => $order->id]),
                description: 'marketplace refund',
                trxData: ['order_id' => $order->id, 'refund' => true],
            );

            $order->update([
                'status'      => OrderStatus::REFUNDED,
                'refunded_at' => now(),
            ]);

            return new SplitResult(order: $order->refresh());
        }, 3);
    }

    private function lockedWallet(int $userId, int $currencyId, bool $create = false): ?Wallet
    {
        $wallet = Wallet::query()
            ->where('user_id', $userId)
            ->where('currency_id', $currencyId)
            ->lockForUpdate()
            ->first();

        if (! $wallet && $create) {
            app(WalletService::class)->createWalletForCurrency(User::findOrFail($userId), $currencyId);

            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->where('currency_id', $currencyId)
                ->lockForUpdate()
                ->first();
        }

        return $wallet;
    }

    private function commissionWalletForOrder(int $currencyId): ?Wallet
    {
        $settings = MarketplaceSetting::current();
        $userId   = $settings?->commission_user_id;

        if (! $userId) {
            return null;
        }

        return $this->lockedWallet((int) $userId, $currencyId);
    }

    /**
     * @param array<string, mixed> $trxData
     */
    private function recordTransaction(
        int $userId,
        TrxType $type,
        float $amount,
        AmountFlow $flow,
        string $currency,
        string $walletUuid,
        string $reference,
        string $remarks,
        string $description,
        array $trxData,
    ): void {
        $this->transactions->create(new TransactionData(
            user_id: $userId,
            trx_type: $type,
            amount: $amount,
            amount_flow: $flow,
            fee: 0,
            currency: $currency,
            provider: 'marketplace',
            processing_type: MethodType::MANUAL,
            net_amount: $amount,
            payable_amount: $amount,
            payable_currency: $currency,
            wallet_reference: $walletUuid,
            trx_reference: $reference,
            trx_data: $trxData,
            remarks: $remarks,
            description: $description,
            status: TrxStatus::COMPLETED,
        ));
    }

    private function toMoney(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 8);
    }
}
