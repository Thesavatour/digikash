<?php

namespace App\Console\Commands;

use App\Support\LicenseGuard;
use Illuminate\Console\Command;

class LicenseHeartbeatCommand extends Command
{
    protected $signature = 'license:heartbeat';

    protected $description = 'Re-verify the project license against the update server and refresh the cached state.';

    public function handle(LicenseGuard $guard): int
    {
        if (! $guard->enforced()) {
            $this->components->info('License enforcement is disabled — nothing to verify.');

            return self::SUCCESS;
        }

        $result = $guard->refresh();

        $this->components->info(sprintf('License state: %s', $result['state']));

        return self::SUCCESS;
    }
}
