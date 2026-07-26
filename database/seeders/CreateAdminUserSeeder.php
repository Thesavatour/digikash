<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Credentials are read from the LOCAL .env (DEMO_ADMIN_EMAIL /
     * DEMO_ADMIN_PASSWORD) so no developer credentials are hardcoded in the
     * committed source. On a fresh client install the installer creates the
     * admin from the buyer's own form input instead — this seeder is a local
     * development convenience. The password falls back to a random string so
     * a usable account is never seeded with a known, shippable password.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Clear existing admin records

        DB::table('admins')->truncate();

        $email    = (string) (config('app.demo_admin_email') ?: env('DEMO_ADMIN_EMAIL', 'admin@example.com'));
        $password = (string) (config('app.demo_admin_password') ?: env('DEMO_ADMIN_PASSWORD', Str::random(16)));

        // Create a super admin user
        $superAdmin = Admin::create([
            'name'     => 'Super Admin',
            'email'    => $email,
            'password' => bcrypt($password),
        ]);

        // Assign the super admin role to the super admin user
        $superAdmin->assignRole('super-admin');
    }
}
