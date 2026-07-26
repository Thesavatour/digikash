<?php

namespace App\Support;

use App\Models\ProjectLicense;
use App\Services\ProjectUpdateServerClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Runtime license guard.
 *
 * This is a deliberately SOFT enforcement layer: a temporary update-server
 * outage never locks a genuine buyer out. Only a server-confirmed invalid
 * license (or domain mismatch) degrades admin write actions.
 *
 * Design notes for maintainers (and a small speed bump for nullers):
 *   - State is cached so normal requests never hit the network.
 *   - Network verification happens on a throttled basis from the admin
 *     middleware and from the scheduled `license:heartbeat` command, so the
 *     check lives in more than one independent place.
 *   - There is no encryption (CodeCanyon-compliant). The real barrier is
 *     that a working install must talk to the update server, which a buyer
 *     does not control.
 */
class LicenseGuard
{
    public const VALID = 'valid';

    public const GRACE = 'grace';

    public const EXPIRED_GRACE = 'expired_grace';

    public const INVALID = 'invalid';

    public const UNMANAGED = 'unmanaged';

    private const CACHE_KEY = 'project_license_guard_state';

    public function __construct(private readonly ProjectUpdateServerClient $client) {}

    /**
     * Whether enforcement is active at all. Disabled on local/testing and
     * when the operator turns it off in config.
     */
    public function enforced(): bool
    {
        return (bool) config('project_updater.license_enforce', true)
            && ! app()->environment('local', 'testing')
            // Demo deployments never run remote license verification, so the
            // runtime guard must stay completely silent — no banner, no paused
            // admin actions.
            && ! (bool) config('app.demo', false);
    }

    /**
     * Current cached license state. Never performs a network call, so it is
     * safe to call on every request (including public traffic).
     */
    public function state(): string
    {
        if (! $this->enforced()) {
            return self::VALID;
        }

        $cache = $this->readCache();

        return is_array($cache) ? (string) ($cache['state'] ?? self::GRACE) : self::GRACE;
    }

    /**
     * Only a server-confirmed invalid license blocks anything. Outages,
     * grace, unmanaged, and valid states never block.
     */
    public function isBlocking(): bool
    {
        return $this->enforced() && $this->state() === self::INVALID;
    }

    /**
     * Refresh from the server if the cached state is missing or older than
     * the recheck window. Throttled with a lock so concurrent admin requests
     * do not stampede, and fully fail-safe — a failure never breaks a page.
     */
    public function refreshIfDue(): void
    {
        if (! $this->enforced()) {
            return;
        }

        $cache   = $this->readCache();
        $recheck = max(1, (int) config('project_updater.license_recheck_hours', 12));
        $due     = ! is_array($cache)
            || ! isset($cache['checked_at'])
            || ((int) $cache['checked_at'] + ($recheck * 3600)) < now()->timestamp;

        if (! $due) {
            return;
        }

        $lock = Cache::lock('project_license_guard_refresh', 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->refresh();
        } catch (Throwable $e) {
            Log::channel('single')->warning('[license] refresh failed: '.$e->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Verify against the update server now and persist the resulting state.
     *
     * @return array{state: string, last_good_at: int|null, checked_at: int}
     */
    public function refresh(): array
    {
        $license = ProjectLicense::current();
        $token   = $license?->license_token;

        if (! $license || blank($token)) {
            return $this->persist(self::UNMANAGED, null);
        }

        $previous = $this->readCache();
        $lastGood = is_array($previous) ? ($previous['last_good_at'] ?? null) : null;

        $result = $this->client->verifyLicense((string) $token, (string) $this->currentDomain());

        switch ($result['outcome']) {
            case 'valid':
                $license->forceFill(['status' => 'active', 'last_checked_at' => now()])->saveQuietly();

                return $this->persist(self::VALID, now()->timestamp);

            case 'invalid':
                $license->forceFill(['last_checked_at' => now()])->saveQuietly();

                return $this->persist(self::INVALID, $lastGood);

            default:
                $graceSeconds = max(0, (int) config('project_updater.license_grace_days', 14)) * 86400;

                if ($lastGood !== null && (now()->timestamp - (int) $lastGood) <= $graceSeconds) {
                    return $this->persist(self::GRACE, $lastGood);
                }

                return $this->persist(self::EXPIRED_GRACE, $lastGood);
        }
    }

    /**
     * Record that the license was just confirmed by a successful activation or
     * refresh. Activation is itself a server-confirmed signal, so we persist
     * VALID immediately — this clears the runtime banner and re-enables admin
     * writes at once, instead of waiting for the next throttled recheck.
     */
    public function markValidated(): void
    {
        if (! $this->enforced()) {
            return;
        }

        $this->persist(self::VALID, now()->timestamp);
    }

    /**
     * Admin banner content for the current state, or null when nothing needs
     * to be shown (valid / grace are silent so outages stay invisible).
     *
     * @return array{tone: string, title: string, message: string, action_url: string|null, action_label: string|null}|null
     */
    public function banner(): ?array
    {
        $actionUrl = Route::has('admin.app.updater.index') ? route('admin.app.updater.index') : null;

        return match ($this->state()) {
            self::INVALID => [
                'tone'         => 'danger',
                'title'        => __('License needs re-activation'),
                'message'      => __('We could not verify your license for this domain. Admin changes are paused until you re-activate it.'),
                'action_url'   => $actionUrl,
                'action_label' => __('Open License Settings'),
            ],
            self::EXPIRED_GRACE => [
                'tone'         => 'warning',
                'title'        => __('License could not be re-verified'),
                'message'      => __('The update server has been unreachable for a while. Please check your server can reach the internet so your license can re-verify.'),
                'action_url'   => $actionUrl,
                'action_label' => __('Check License'),
            ],
            self::UNMANAGED => [
                'tone'         => 'warning',
                'title'        => __('License not activated'),
                'message'      => __('Activate your license to keep receiving updates and support for this installation.'),
                'action_url'   => $actionUrl,
                'action_label' => __('Activate License'),
            ],
            default => null,
        };
    }

    private function currentDomain(): string
    {
        $host = request()->getHost();

        if (filled($host)) {
            return (string) $host;
        }

        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
    }

    /**
     * @return array{state: string, last_good_at: int|null, checked_at: int}|null
     */
    private function readCache(): ?array
    {
        $value = Cache::get(self::CACHE_KEY);

        return is_array($value) ? $value : null;
    }

    /**
     * @return array{state: string, last_good_at: int|null, checked_at: int}
     */
    private function persist(string $state, ?int $lastGood): array
    {
        $payload = [
            'state'        => $state,
            'last_good_at' => $lastGood,
            'checked_at'   => now()->timestamp,
        ];

        Cache::forever(self::CACHE_KEY, $payload);

        return $payload;
    }
}
