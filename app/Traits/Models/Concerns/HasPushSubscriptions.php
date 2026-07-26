<?php

namespace App\Traits\Models\Concerns;

use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPushSubscriptions
{
    public function pushSubscriptions(): MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }

    /**
     * @param  array{endpoint:string,keys?:array{p256dh?:string,auth?:string},expirationTime?:mixed}  $subscription
     */
    public function updatePushSubscription(array $subscription, ?string $contentEncoding = null, ?string $userAgent = null): PushSubscription
    {
        $endpoint = $subscription['endpoint'] ?? '';
        $keys     = $subscription['keys'] ?? [];

        return $this->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'public_key'       => $keys['p256dh'] ?? null,
                'auth_token'       => $keys['auth'] ?? null,
                'content_encoding' => $contentEncoding ?: 'aes128gcm',
                'user_agent'       => $userAgent,
                'last_used_at'     => now(),
            ]
        );
    }

    public function deletePushSubscription(string $endpoint): void
    {
        $this->pushSubscriptions()->where('endpoint', $endpoint)->delete();
    }
}
