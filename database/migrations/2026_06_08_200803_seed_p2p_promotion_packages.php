<?php

declare(strict_types=1);

use App\Models\P2P\PromotionPackage;
use Database\Seeders\P2PPromotionPackageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the bundled P2P promotion packages on existing installs.
 *
 * Why this migration exists:
 *
 *   P2PPromotionPackageSeeder ships six default promotion plans, but it was
 *   never registered in DatabaseSeeder — so any environment provisioned
 *   before this fix has an EMPTY `p2p_promotion_packages` table. With no
 *   packages, the admin "Promotion Plans" screen looks broken/missing and
 *   users have nothing to buy when promoting a trade ad.
 *
 *   Registering the seeder fixes fresh installs, but existing servers don't
 *   re-run db:seed on deploy. This migration closes that gap: on the next
 *   `php artisan migrate` the default plans appear automatically.
 *
 * Safety:
 *
 *   - Runs only when the table exists AND is currently empty, so it never
 *     overwrites packages an admin has already created or customised.
 *   - The seeder itself is idempotent (updateOrCreate keyed on name), but
 *     the empty-table guard keeps this strictly additive.
 *   - down() is a deliberate no-op: promotion plans are admin-managed
 *     runtime data and must survive a rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('p2p_promotion_packages')) {
            return;
        }

        if (PromotionPackage::query()->exists()) {
            return;
        }

        (new P2PPromotionPackageSeeder)->run();
    }

    public function down(): void
    {
        // Intentionally empty — promotion plans are admin-managed data.
    }
};
