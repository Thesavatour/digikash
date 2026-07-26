<?php

namespace App\Providers;

use App\Services\ProjectUpdateServerClient;
use App\Support\LicenseGuard;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the runtime license guard as a shared singleton.
 *
 * Kept intentionally thin and network-free: the guard only talks to the
 * update server from the admin middleware and the scheduled heartbeat, so
 * booting this provider never adds latency or breaks early-boot requests
 * (including the installer and the test suite).
 */
class LicenseGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LicenseGuard::class, fn ($app) => new LicenseGuard(
            $app->make(ProjectUpdateServerClient::class)
        ));
    }

    public function boot(): void
    {
        //
    }
}
