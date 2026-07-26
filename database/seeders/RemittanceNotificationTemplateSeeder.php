<?php

namespace Database\Seeders;

use App\Enums\NotificationActionType;
use App\Enums\UserType;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class RemittanceNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'identifier'  => 'remittance_user_submitted',
                'name'        => 'Remittance Submitted',
                'icon'        => 'send-money',
                'action_type' => NotificationActionType::REQUESTED,
                'info'        => 'User is notified after a remittance transfer is submitted and the wallet has been debited.',
                'user_type'   => UserType::USER,
                'variables'   => ['amount', 'beneficiary', 'trx'],
                'channels'    => [
                    [
                        'channel'   => 'email',
                        'title'     => 'Your transfer is on the way',
                        'message'   => 'Your transfer of {amount} to {beneficiary} has been submitted. Reference: {trx}. You can track it from your dashboard.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'push',
                        'title'     => 'Transfer submitted',
                        'message'   => '{amount} sent to {beneficiary}. Tracking ref {trx}.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'sms',
                        'title'     => null,
                        'message'   => 'Transfer {amount} to {beneficiary} submitted. Ref {trx}.',
                        'is_active' => false,
                    ],
                ],
            ],
            [
                'identifier'  => 'remittance_user_completed',
                'name'        => 'Remittance Completed',
                'icon'        => 'card-approved',
                'action_type' => NotificationActionType::COMPLETED,
                'info'        => 'User is notified when an international transfer has been paid out to the beneficiary.',
                'user_type'   => UserType::USER,
                'variables'   => ['amount', 'beneficiary', 'trx'],
                'channels'    => [
                    [
                        'channel'   => 'email',
                        'title'     => 'Your transfer has arrived',
                        'message'   => '{beneficiary} has received {amount}. Reference {trx}. Thanks for using our service.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'push',
                        'title'     => 'Transfer completed',
                        'message'   => '{beneficiary} received {amount}.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'sms',
                        'title'     => null,
                        'message'   => 'Transfer complete: {beneficiary} received {amount}. Ref {trx}.',
                        'is_active' => false,
                    ],
                ],
            ],
            [
                'identifier'  => 'remittance_user_failed',
                'name'        => 'Remittance Failed',
                'icon'        => 'refund',
                'action_type' => NotificationActionType::FAILED,
                'info'        => 'User is notified when a remittance transfer has failed at the payout partner.',
                'user_type'   => UserType::USER,
                'variables'   => ['amount', 'beneficiary', 'reason', 'trx'],
                'channels'    => [
                    [
                        'channel'   => 'email',
                        'title'     => 'Your transfer could not be completed',
                        'message'   => 'Your transfer of {amount} to {beneficiary} failed. Reason: {reason}. Reference {trx}. Your funds will be returned to your wallet.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'push',
                        'title'     => 'Transfer failed',
                        'message'   => '{amount} to {beneficiary} failed. {reason}.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'sms',
                        'title'     => null,
                        'message'   => 'Transfer to {beneficiary} failed: {reason}. Ref {trx}.',
                        'is_active' => false,
                    ],
                ],
            ],
            [
                'identifier'  => 'remittance_admin_submitted',
                'name'        => 'Remittance Admin Alert',
                'icon'        => 'send-money',
                'action_type' => NotificationActionType::REQUESTED,
                'info'        => 'Admin is alerted when a new remittance transfer is submitted and may need manual approval.',
                'user_type'   => UserType::ADMIN,
                'variables'   => ['user', 'amount', 'beneficiary', 'trx'],
                'channels'    => [
                    [
                        'channel'   => 'email',
                        'title'     => 'New remittance transfer submitted',
                        'message'   => '{user} submitted a {amount} transfer to {beneficiary}. Reference {trx}. Review it in the admin panel.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'push',
                        'title'     => 'Remittance pending',
                        'message'   => '{user} sent {amount} to {beneficiary}.',
                        'is_active' => true,
                    ],
                    [
                        'channel'   => 'sms',
                        'title'     => null,
                        'message'   => 'Remittance: {user} sent {amount} to {beneficiary}. Ref {trx}.',
                        'is_active' => false,
                    ],
                ],
            ],
        ];

        foreach ($templates as $data) {
            $template = NotificationTemplate::updateOrCreate(
                ['identifier' => $data['identifier']],
                collect($data)->except('channels')->toArray()
            );

            $template->channels()->delete();
            $template->channels()->createMany($data['channels']);
        }
    }
}
