<?php

declare(strict_types=1);

use App\Services\FeatureManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantees the "remittance" feature (and any other catalog entry added
 * after the original FeatureSeeder run) is present in Feature Management
 * on every environment.
 *
 * Why this migration exists:
 *
 *   The `remittance` feature was added to config/feature_catalog.php after
 *   the `features` table had already been seeded on existing installs.
 *   FeatureManager::sync() only inserts catalog rows when it runs, and the
 *   resolved catalog is cached for 24h (feature_manager.catalog.v1). So on
 *   any environment seeded before remittance landed, the admin "Feature
 *   Management" screen showed a stale list with no Remittance entry —
 *   even though the routes, admin menu, and permissions were all wired.
 *
 * What this does (idempotent + deploy-safe):
 *
 *   1. sync() — re-reads config/feature_catalog.php and inserts any missing
 *      feature rows + their per-panel access rules via firstOrNew(), so it
 *      never duplicates existing rows and never overwrites admin-controlled
 *      toggles (is_enabled, conditions) on features that already exist.
 *   2. flush() — clears the cached catalog so the new row appears in the
 *      admin UI immediately, without waiting for the 24h TTL.
 *
 * Running it again is harmless: firstOrNew keeps it a no-op once synced.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guard: only sync once the feature storage actually exists. On a
        // fresh install the create-table migrations run first, so this is
        // satisfied; on an in-place deploy the tables are already present.
        if (! Schema::hasTable('features') || ! Schema::hasTable('feature_access_rules')) {
            return;
        }

        $manager = app(FeatureManager::class);
        $manager->sync();
        $manager->flush();
    }

    public function down(): void
    {
        // Intentionally a no-op. The remittance feature row (and its access
        // rules) are admin-managed runtime data — rolling back this
        // migration must never destroy a feature the operator may have
        // configured. Removing the feature is an explicit admin action in
        // Feature Management, not a schema rollback concern.
    }
};
