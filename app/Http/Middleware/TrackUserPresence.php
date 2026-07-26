<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserPresence
{
    /**
     * Stamp the authenticated frontend user's `last_seen_at` so the UI can show
     * a live online/offline presence dot. A per-user cache lock throttles the
     * write to at most once per minute, keeping this off the hot path.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $cacheKey = 'user-presence-seen-'.$user->getKey();

            if (Cache::add($cacheKey, true, now()->addMinute())) {
                // Stamp presence without bumping `updated_at` or firing model
                // events — this is a high-frequency side-write, not a real edit.
                User::withoutTimestamps(function () use ($user) {
                    $user->forceFill(['last_seen_at' => now()])->saveQuietly();
                });
            }
        }

        return $next($request);
    }
}
