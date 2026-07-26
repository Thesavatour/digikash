<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WebPush\WebPushSender;
use Illuminate\Console\Command;

class SendTestWebPushCommand extends Command
{
    protected $signature = 'webpush:test {user : User ID or email} {--title=DigiKash} {--body=Test device notification}';

    protected $description = 'Send a test OS-level Web Push notification to a user\'s subscribed devices';

    public function handle(WebPushSender $sender): int
    {
        if (! $sender->isConfigured()) {
            $this->error('VAPID keys are not configured. Run: php artisan webpush:vapid');

            return self::FAILURE;
        }

        $lookup = $this->argument('user');
        $user   = is_numeric($lookup)
            ? User::query()->find($lookup)
            : User::query()->where('email', $lookup)->first();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            $this->warn('User has no push subscriptions. Enable device notifications in the PWA first.');

            return self::FAILURE;
        }

        $payload = [
            'title' => (string) $this->option('title'),
            'body'  => (string) $this->option('body'),
            'icon'  => url('/favicon.ico'),
            'badge' => url('/favicon.ico'),
            'data'  => ['url' => url('/user/notifications')],
            'tag'   => 'digikash-webpush-test',
        ];

        $ok = 0;
        foreach ($subscriptions as $subscription) {
            $result = $sender->send([
                'endpoint'         => $subscription->endpoint,
                'public_key'       => $subscription->public_key,
                'auth_token'       => $subscription->auth_token,
                'content_encoding' => $subscription->content_encoding ?: 'aes128gcm',
            ], $payload);

            if ($result['expired']) {
                $subscription->delete();
                $this->warn('Removed expired subscription.');

                continue;
            }

            if ($result['success']) {
                $ok++;
                $this->info('Sent (HTTP '.$result['status'].')');
            } else {
                $this->error('Failed HTTP '.$result['status'].': '.$result['body']);
            }
        }

        return $ok > 0 ? self::SUCCESS : self::FAILURE;
    }
}
