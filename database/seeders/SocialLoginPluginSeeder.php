<?php

namespace Database\Seeders;

use App\Models\Plugin;
use Illuminate\Database\Seeder;

/**
 * Seed Google / Apple Sign-In plugins for Admin → Integration Center.
 * Credentials and on/off status are managed in the admin plugin UI.
 *
 * Local/demo installs ship with `demo-*` client IDs so Continue with
 * Google/Apple works without real OAuth apps. Replace those values with
 * production credentials before going live.
 */
class SocialLoginPluginSeeder extends Seeder
{
    public function run(): void
    {
        $plugins = [
            [
                'name'         => 'Google Sign-In',
                'code'         => 'google-login',
                'logo'         => 'general/static/plugins/google-login.svg',
                'description'  => 'Allow users to continue with Google on the user login and registration screens. Use client_id starting with "demo" for local demo login without Google Cloud.',
                'status'       => 1,
                'fields_blade' => '_social_login_google',
                'credentials'  => [
                    'client_id'     => 'demo-google',
                    'client_secret' => 'demo-google-secret',
                    'redirect'      => '',
                ],
            ],
            [
                'name'         => 'Apple Sign-In',
                'code'         => 'apple-login',
                'logo'         => 'general/static/plugins/apple-login.svg',
                'description'  => 'Allow users to continue with Apple on the user login and registration screens. Use client_id starting with "demo" for local demo login without Apple Developer.',
                'status'       => 1,
                'fields_blade' => '_social_login_apple',
                'credentials'  => [
                    'client_id'   => 'demo-apple',
                    'team_id'     => 'DEMOTEAMID',
                    'key_id'      => 'DEMOKEYID',
                    'private_key' => 'demo-private-key',
                    'redirect'    => '',
                ],
            ],
        ];

        foreach ($plugins as $payload) {
            Plugin::query()->updateOrCreate(
                ['code' => $payload['code']],
                [
                    'type'         => Plugin::TYPE_SOCIAL_LOGIN,
                    'name'         => $payload['name'],
                    'logo'         => $payload['logo'],
                    'description'  => $payload['description'],
                    'fields_blade' => $payload['fields_blade'] ?? null,
                    'credentials'  => json_encode($payload['credentials']),
                    'status'       => $payload['status'],
                ],
            );
        }
    }
}
