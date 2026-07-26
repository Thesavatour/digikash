<?php

namespace App\Services\Remittance;

use App\Constants\CurrencyRole;
use App\Data\TransactionData;
use App\Enums\AmountFlow;
use App\Enums\MethodType;
use App\Enums\RemittanceStatus;
use App\Enums\TrxStatus;
use App\Enums\TrxType;
use App\Models\Beneficiary;
use App\Models\RemittanceQuote;
use App\Models\RemittanceTransfer;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet as WalletModel;
use App\Services\Handlers\RemittanceHandler;
use App\Services\Payment\Payout\PayoutGatewayFactory;
use App\Services\Payment\Payout\PayoutResult;
use Exception;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Wallet;

/**
 * Orchestrates the full Tap-Tap-Send style international transfer:
 * validate quote → screen → debit wallet → record transaction → call
 * payout gateway → update transfer with outcome.
 */
class RemittanceService
{
    public function __construct(
        protected ScreeningService $screening,
        protected PayoutGatewayFactory $gatewayFactory,
        protected RemittanceHandler $handler,
    ) {}

    /**
     * Confirm a remittance against a previously generated quote.
     *
     * @param array{purpose?: ?string, source_of_funds?: ?string, wallet_id: int} $context
     *
     * @throws Throwable
     */
    public function send(User $user, RemittanceQuote $quote, Beneficiary $beneficiary, array $context): RemittanceTransfer
    {
        $this->assertQuoteUsable($quote, $user, $beneficiary);

        $senderWallet = $this->resolveSenderWallet($user, (int) $context['wallet_id'], $quote->source_currency);

        $compliance = $this->screening->screen(
            sender: $user,
            beneficiary: $beneficiary,
            sendAmountSourceCurrency: $quote->send_amount,
            requiredKycTier: (int) ($quote->corridor->required_kyc_tier ?? 0),
        );

        if (! $compliance['passed']) {
            throw new RuntimeException($compliance['reason'] ?? __('Compliance screening failed.'));
        }

        if (! Wallet::isWalletBalanceSufficient($senderWallet->uuid, $quote->total_pay)) {
            throw new RuntimeException(__('Insufficient balance.'));
        }

        /** @var RemittanceTransfer $transfer */
        $transfer = DB::transaction(function () use ($user, $quote, $beneficiary, $senderWallet, $compliance, $context) {
            // Re-fetch the quote with a row lock so two concurrent confirm
            // requests can't both pass the is-consumed check.
            $lockedQuote = RemittanceQuote::lockForUpdate()->find($quote->id);

            if (! $lockedQuote || ! $lockedQuote->isUsable()) {
                throw new RuntimeException(__('This quote has expired or has already been used. Please get a fresh quote.'));
            }

            Wallet::subtractMoneyByWalletUuid($senderWallet->uuid, $quote->total_pay);

            $transactionData = new TransactionData(
                user_id: $user->id,
                trx_type: TrxType::REMITTANCE_SEND,
                amount: $quote->send_amount,
                amount_flow: AmountFlow::MINUS,
                fee: $quote->fee_amount,
                currency: $quote->source_currency,
                provider: $quote->corridor->payout_gateway,
                processing_type: MethodType::AUTOMATIC,
                net_amount: $quote->send_amount,
                payable_amount: $quote->total_pay,
                payable_currency: $senderWallet->currency->code,
                wallet_reference: $senderWallet->uuid,
                trx_data: [
                    'quote_id'             => $quote->quote_id,
                    'beneficiary_id'       => $beneficiary->id,
                    'beneficiary_name'     => $beneficiary->full_name,
                    'destination_country'  => $beneficiary->country_code,
                    'destination_currency' => $quote->destination_currency,
                    'receive_amount'       => $quote->receive_amount,
                    'exchange_rate'        => $quote->exchange_rate,
                ],
                description: __('Sending :amount :currency to :name (:country)', [
                    'amount'   => $quote->send_amount,
                    'currency' => $quote->source_currency,
                    'name'     => $beneficiary->full_name,
                    'country'  => $beneficiary->country_code,
                ]),
                status: TrxStatus::PENDING,
            );

            $transaction = Transaction::create($transactionData);

            $transfer = RemittanceTransfer::create([
                'user_id'              => $user->id,
                'quote_id'             => $quote->id,
                'beneficiary_id'       => $beneficiary->id,
                'corridor_id'          => $quote->corridor_id,
                'transaction_id'       => $transaction->id,
                'source_currency'      => $quote->source_currency,
                'destination_currency' => $quote->destination_currency,
                'send_amount'          => $quote->send_amount,
                'fee_amount'           => $quote->fee_amount,
                'total_paid'           => $quote->total_pay,
                'exchange_rate'        => $quote->exchange_rate,
                'receive_amount'       => $quote->receive_amount,
                'payout_method'        => $quote->payout_method,
                'payout_gateway'       => $quote->corridor->payout_gateway,
                'status'               => RemittanceStatus::Pending,
                'compliance_result'    => $compliance,
                'purpose'              => $context['purpose']         ?? null,
                'source_of_funds'      => $context['source_of_funds'] ?? null,
            ]);

            $transfer->recordStatus(RemittanceStatus::Pending, __('Wallet debited; awaiting payout submission.'));

            $lockedQuote->update(['is_consumed' => true]);
            $beneficiary->update(['last_used_at' => now()]);

            return $transfer;
        });

        $this->handler->handleSubmitted($transfer->transaction);

        $this->dispatchToGateway($transfer);

        return $transfer->fresh();
    }

    /**
     * Hand off a pending transfer to its payout driver and react to the result.
     */
    public function dispatchToGateway(RemittanceTransfer $transfer): RemittanceTransfer
    {
        try {
            $gateway = $this->gatewayFactory->getGateway($transfer->payout_gateway);
            $result  = $gateway->payout($transfer);
        } catch (Throwable $e) {
            report($e);
            $result = new PayoutResult(
                status: RemittanceStatus::Failed,
                message: $e->getMessage(),
            );
        }

        $transfer->update([
            'gateway_reference' => $result->gatewayReference ?? $transfer->gateway_reference,
            'gateway_payload'   => $result->payload,
        ]);

        $transfer->recordStatus($result->status, $result->message);

        $this->reconcileTransaction($transfer, $result);

        return $transfer->fresh();
    }

    /**
     * Refund a pending/failed transfer back to the sender wallet.
     *
     * @throws Throwable
     */
    public function refund(RemittanceTransfer $transfer, ?string $reason = null): RemittanceTransfer
    {
        if ($transfer->status === RemittanceStatus::Completed) {
            throw new RuntimeException(__('Completed transfers cannot be auto-refunded.'));
        }

        if ($transfer->status === RemittanceStatus::Refunded) {
            throw new RuntimeException(__('This transfer has already been refunded.'));
        }

        DB::transaction(function () use ($transfer, $reason): void {
            // Re-fetch with row lock to defend against concurrent refunds.
            $lockedTransfer = RemittanceTransfer::lockForUpdate()->find($transfer->id);

            if (! $lockedTransfer || $lockedTransfer->status === RemittanceStatus::Refunded) {
                throw new RuntimeException(__('This transfer has already been refunded.'));
            }

            if ($lockedTransfer->status === RemittanceStatus::Completed) {
                throw new RuntimeException(__('Completed transfers cannot be auto-refunded.'));
            }

            $originalTrx = $lockedTransfer->transaction;

            if ($originalTrx) {
                Wallet::addMoneyByWalletUuid($originalTrx->wallet_reference, $lockedTransfer->total_paid);

                $refundData = new TransactionData(
                    user_id: $lockedTransfer->user_id,
                    trx_type: TrxType::REMITTANCE_REFUND,
                    amount: $lockedTransfer->total_paid,
                    amount_flow: AmountFlow::PLUS,
                    fee: 0,
                    currency: $lockedTransfer->source_currency,
                    provider: $lockedTransfer->payout_gateway,
                    processing_type: MethodType::AUTOMATIC,
                    net_amount: $lockedTransfer->total_paid,
                    payable_amount: $lockedTransfer->total_paid,
                    payable_currency: $lockedTransfer->source_currency,
                    wallet_reference: $originalTrx->wallet_reference,
                    trx_reference: (string) $originalTrx->id,
                    trx_data: [
                        'transfer_reference' => $lockedTransfer->reference,
                        'reason'             => $reason,
                    ],
                    description: __('Refund for remittance :ref', ['ref' => $lockedTransfer->reference]),
                    status: TrxStatus::COMPLETED,
                );

                Transaction::create($refundData);

                $originalTrx->update(['status' => TrxStatus::CANCELED]);
            }

            $lockedTransfer->recordStatus(RemittanceStatus::Refunded, $reason ?? __('Refunded to sender wallet.'));
        });

        return $transfer->fresh();
    }

    protected function reconcileTransaction(RemittanceTransfer $transfer, PayoutResult $result): void
    {
        $transaction = $transfer->transaction?->fresh();

        if (! $transaction) {
            return;
        }

        if ($result->status === RemittanceStatus::Completed) {
            $transaction->update(['status' => TrxStatus::COMPLETED]);
            $this->handler->handleSuccess($transaction);

            return;
        }

        if ($result->isFailed()) {
            $transaction->update([
                'status'  => TrxStatus::FAILED,
                'remarks' => $result->message,
            ]);
            $this->handler->handleFail($transaction);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function assertQuoteUsable(RemittanceQuote $quote, User $user, Beneficiary $beneficiary): void
    {
        if ($quote->user_id !== $user->id) {
            throw new InvalidArgumentException(__('Quote does not belong to current user.'));
        }

        if (! $quote->isUsable()) {
            throw new InvalidArgumentException(__('This quote has expired or has already been used. Please get a fresh quote.'));
        }

        if ($beneficiary->user_id !== $user->id) {
            throw new InvalidArgumentException(__('Beneficiary does not belong to current user.'));
        }

        if (strtoupper($beneficiary->currency) !== strtoupper($quote->destination_currency)) {
            throw new InvalidArgumentException(__('Beneficiary currency does not match the quote destination currency.'));
        }
    }

    /**
     * @throws Exception
     */
    protected function resolveSenderWallet(User $user, int $walletId, string $sourceCurrency): WalletModel
    {
        $wallet = $user->wallets()
            ->with(['currency.roles'])
            ->find($walletId);

        if (! $wallet) {
            throw new InvalidArgumentException(__('Selected wallet was not found.'));
        }

        if (! $wallet->status || ! $wallet->hasCurrencyRole(CurrencyRole::SENDER)) {
            throw new InvalidArgumentException(__('Selected wallet cannot send money.'));
        }

        if (strtoupper($wallet->currency->code) !== strtoupper($sourceCurrency)) {
            throw new InvalidArgumentException(__('Wallet currency does not match the quote source currency.'));
        }

        return $wallet;
    }
}
