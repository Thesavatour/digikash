<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Services\WebPush\WebPushSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebPushChannel
{
    public function __construct(protected WebPushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $this->sender->isConfigured()) {
            return;
        }

        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (empty($payload)) {
            return;
        }

        if (! method_exists($notifiable, 'pushSubscriptions')) {
            return;
        }

        $subscriptions = $notifiable->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        foreach ($subscriptions as $subscription) {
            /** @var PushSubscription $subscription */
            try {
                $result = $this->sender->send([
                    'endpoint'         => $subscription->endpoint,
                    'public_key'       => $subscription->public_key,
                    'auth_token'       => $subscription->auth_token,
                    'content_encoding' => $subscription->content_encoding ?: 'aes128gcm',
                ], $payload);

                if ($result['expired']) {
                    $subscription->delete();

                    continue;
                }

                if ($result['success']) {
                    $subscription->forceFill(['last_used_at' => now()])->save();
                } else {
                    Log::warning('Web Push delivery failed', [
                        'status'   => $result['status'],
                        'body'     => $result['body'],
                        'endpoint' => substr($subscription->endpoint, 0, 80),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Web Push send exception: '.$e->getMessage());
            }
        }
    }
}
