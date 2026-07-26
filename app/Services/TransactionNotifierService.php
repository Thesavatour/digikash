<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Transaction;
use App\Notifications\TemplateNotification;
use Illuminate\Support\Facades\Notification;

class TransactionNotifierService
{
    /**
     * Notify a user using a specific template.
     *
     * When $action is omitted, deep-link to the transaction receipt so OS push
     * and in-app notification taps open the matching wallet history item.
     */
    public function toUser(Transaction $trx, string $identifier, array $data, $action = null): void
    {
        if (! isset($data['trx']) && filled($trx->trx_id)) {
            $data['trx'] = $trx->trx_id;
        }

        if ($action === null && filled($trx->trx_id)) {
            $action = user_transaction_receipt_url($trx->trx_id);
        }

        $trx->user->notify(new TemplateNotification(
            identifier: $identifier,
            data: $data,
            action: $action
        ));
    }

    /**
     * Notify all admins who have specific permission using a template.
     */
    public function toAdmins(string $permission, string $identifier, array $data, $sender = null, ?string $action = null): void
    {
        $admins = Admin::permission($permission)->get();

        Notification::send($admins, new TemplateNotification(
            identifier: $identifier,
            data: $data,
            sender: $sender,
            action: $action
        ));
    }
}
