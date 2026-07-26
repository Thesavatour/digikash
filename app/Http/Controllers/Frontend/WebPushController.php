<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\WebPush\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebPushController extends Controller
{
    public function vapidPublicKey(WebPushSender $sender): JsonResponse
    {
        if (! $sender->isConfigured()) {
            return response()->json([
                'enabled'    => false,
                'public_key' => null,
            ]);
        }

        return response()->json([
            'enabled'    => true,
            'public_key' => config('webpush.vapid.public_key'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'    => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
            'encoding'    => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();
        if (! method_exists($user, 'updatePushSubscription')) {
            return response()->json(['message' => 'Push subscriptions are not supported for this account.'], 422);
        }

        $user->updatePushSubscription(
            [
                'endpoint' => $data['endpoint'],
                'keys'     => $data['keys'],
            ],
            $data['encoding'] ?? 'aes128gcm',
            substr((string) $request->userAgent(), 0, 500)
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
        ]);

        $user = $request->user();
        if (method_exists($user, 'deletePushSubscription')) {
            $user->deletePushSubscription($data['endpoint']);
        }

        return response()->json(['ok' => true]);
    }
}
