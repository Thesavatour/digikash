<?php

namespace App\Services\Handlers;

use App\Models\Beneficiary;
use App\Models\Transaction;
use App\Services\Handlers\Interfaces\FailHandlerInterface;
use App\Services\Handlers\Interfaces\SubmittedHandlerInterface;
use App\Services\Handlers\Interfaces\SuccessHandlerInterface;
use App\Services\TransactionNotifierService;

class RemittanceHandler implements FailHandlerInterface, SubmittedHandlerInterface, SuccessHandlerInterface
{
    public function __construct(protected TransactionNotifierService $notifier) {}

    public function handleSubmitted(Transaction $transaction): void
    {
        $this->notifier->toUser($transaction, 'remittance_user_submitted', [
            'amount'      => $transaction->amount.' '.$transaction->currency,
            'beneficiary' => $this->beneficiaryName($transaction),
            'trx'         => $transaction->trx_id,
        ]);

        $this->notifier->toAdmins('remittance-notification', 'remittance_admin_submitted', [
            'user'        => $transaction->user->name,
            'amount'      => $transaction->amount.' '.$transaction->currency,
            'beneficiary' => $this->beneficiaryName($transaction),
            'trx'         => $transaction->trx_id,
        ], sender: $transaction->user);
    }

    public function handleSuccess(Transaction $transaction): void
    {
        $this->notifier->toUser($transaction, 'remittance_user_completed', [
            'amount'      => $transaction->amount.' '.$transaction->currency,
            'beneficiary' => $this->beneficiaryName($transaction),
            'trx'         => $transaction->trx_id,
        ]);
    }

    public function handleFail(Transaction $transaction): void
    {
        $this->notifier->toUser($transaction, 'remittance_user_failed', [
            'amount'      => $transaction->amount.' '.$transaction->currency,
            'beneficiary' => $this->beneficiaryName($transaction),
            'reason'      => $transaction->remarks ?? __('Unknown error'),
            'trx'         => $transaction->trx_id,
        ]);
    }

    protected function beneficiaryName(Transaction $transaction): string
    {
        $name = $transaction->trx_data['beneficiary_name'] ?? null;

        if ($name) {
            return $name;
        }

        $beneficiaryId = $transaction->trx_data['beneficiary_id'] ?? null;

        if ($beneficiaryId) {
            return Beneficiary::find($beneficiaryId)?->full_name ?? __('Beneficiary');
        }

        return __('Beneficiary');
    }
}
