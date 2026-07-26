<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\Backend\LinkStorageRequest;
use App\Http\Requests\Backend\UpdateSettingRequest;
use App\Services\SettingService;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;

class SettingController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'index'       => 'site-setting-view',
            'update'      => 'site-setting-update',
            'linkStorage' => 'site-setting-view|site-setting-update',
        ];
    }

    /**
     * Display a listing of the settings.
     *
     * @return Factory
     */
    public function index()
    {
        // Fetch settings from the configuration file
        $settings = config('settings');
        unset($settings['hide_settings']);

        $settings = collect([
            'general_settings',
            'role_branding_settings',
            'site_security',
            'mail_settings',
            'notification_settings',
            'signup_bonus_settings',
            'agent_settings',
            'kyc_settings',
            'pwa_settings',
            'cookie_settings',
            'maintenance_mode',
        ])
            ->filter(fn (string $section) => isset($settings[$section]))
            ->mapWithKeys(fn (string $section) => [$section => $settings[$section]])
            ->toArray();

        // Stamp dynamic units that depend on runtime state (e.g. site
        // currency). Done here rather than in config/settings.php
        // because the helper resolves a container service that isn't
        // available during the early config-load phase.
        $settings = $this->stampDynamicUnits($settings);

        // Map over the settings array to extract the 'icon' key from each setting
        $settingMenus = array_map(function ($setting) {
            return $setting['icon'];
        }, $settings);

        $storageLinkStatus = $this->storageLinkStatus();

        // Return the view with the settings and setting menus
        return view('backend.settings.site.index', compact('settings', 'settingMenus', 'storageLinkStatus'));
    }

    /**
     * Replace any field whose unit is the placeholder `__SITE_CURRENCY__`
     * (or simply needs to mirror the site currency) with the live code
     * from {@see siteCurrency()}. Keeps the rest of the config untouched.
     *
     * @param  array<string, array<string, mixed>> $settings
     * @return array<string, array<string, mixed>>
     */
    protected function stampDynamicUnits(array $settings): array
    {
        $currency = strtoupper((string) (siteCurrency() ?: 'USD'));

        $currencyKeys = [
            'signup_bonus_user_amount',
            'signup_bonus_merchant_amount',
            'signup_bonus_agent_amount',
        ];

        foreach ($settings as $sectionKey => &$section) {
            if (empty($section['elements']) || ! is_array($section['elements'])) {
                continue;
            }

            foreach ($section['elements'] as &$element) {
                if (in_array($element['key'] ?? null, $currencyKeys, true)) {
                    $element['unit'] = $currency;
                }
            }
            unset($element);
        }
        unset($section);

        return $settings;
    }

    public function update($section, UpdateSettingRequest $request, SettingService $settingService)
    {

        try {
            // Update settings using the service
            $settingService->update($section, $request);

            // Build the success message
            $message = __('Settings Updated Successfully');

            // Notify the user of the success
            notifyEvs('success', $message);

            // Redirect back with the section
            return redirect()->back()->with('section', $section);

        } catch (Exception $e) {
            // Notify the user of the error
            notifyEvs('error', $e->getMessage());

            // Redirect back
            return redirect()->back()->with('section', $section);
        }
    }

    public function linkStorage(LinkStorageRequest $request): RedirectResponse
    {
        $functions = $this->storageFunctionStatus();

        if (! $functions['symlink']) {
            notifyEvs('error', __('The PHP symlink() function is disabled on this server. Remove it from disable_functions in php.ini, restart PHP, then try again.'));

            return redirect()->back()->with('section', 'general_settings');
        }

        try {
            File::ensureDirectoryExists(storage_path('app/public'));

            $this->removeExistingStorageLink();

            Artisan::call('storage:link', ['--force' => true]);

            // storage:link returns exit code 0 even when it prints
            // "The [...] link already exists." — so the exit code cannot be
            // trusted. The only reliable signal is whether the public link
            // now actually resolves to the storage target.
            if ($this->storageLinkStatus()['linked']) {
                notifyEvs('success', __('Storage link regenerated successfully.'));
            } else {
                notifyEvs('error', __('Storage link could not be created. Check that the public folder is writable and the PHP exec()/symlink() functions are enabled.'));
            }
        } catch (Throwable $e) {
            notifyEvs('error', __('Storage link could not be created: :message', ['message' => $e->getMessage()]));
        }

        return redirect()->back()->with('section', 'general_settings');
    }

    /**
     * Remove the existing public/storage link before recreating it.
     *
     * Laravel's `storage:unlink` is attempted first, but it does not always
     * remove a directory-style symlink on Windows, so a manual fallback
     * covers symlinks (unlink) and Windows junctions/directories (rmdir).
     */
    private function removeExistingStorageLink(): void
    {
        $publicPath = public_path('storage');

        try {
            Artisan::call('storage:unlink');
        } catch (Throwable) {
            //
        }

        if (! File::exists($publicPath) && ! is_link($publicPath)) {
            return;
        }

        if (is_link($publicPath)) {
            @unlink($publicPath);
        }

        if (File::exists($publicPath)) {
            if (File::isDirectory($publicPath)) {
                @rmdir($publicPath);
            } else {
                @unlink($publicPath);
            }
        }
    }

    /**
     * @return array{linked: bool, public_path: string, target_path: string, status_label: string, status_tone: string, functions: array{exec: bool, symlink: bool}, functions_ok: bool}
     */
    private function storageLinkStatus(): array
    {
        $publicPath = public_path('storage');
        $targetPath = storage_path('app/public');
        $publicReal = realpath($publicPath);
        $targetReal = realpath($targetPath);
        $linked     = is_link($publicPath) || ($publicReal !== false && $targetReal !== false && $publicReal === $targetReal);
        $exists     = File::exists($publicPath);
        $functions  = $this->storageFunctionStatus();

        return [
            'linked'       => $linked,
            'public_path'  => $publicPath,
            'target_path'  => $targetPath,
            'status_label' => $linked ? __('Linked') : ($exists ? __('Needs Repair') : __('Missing')),
            'status_tone'  => $linked ? 'success' : ($exists ? 'warning' : 'danger'),
            'functions'    => $functions,
            'functions_ok' => $functions['exec'] && $functions['symlink'],
        ];
    }

    /**
     * Report whether the PHP functions needed to create the storage link are
     * enabled (present and not listed in disable_functions).
     *
     * @return array{exec: bool, symlink: bool}
     */
    private function storageFunctionStatus(): array
    {
        $disabled = array_map(
            static fn (string $name): string => strtolower(trim($name)),
            explode(',', (string) ini_get('disable_functions'))
        );

        $enabled = static fn (string $function): bool => function_exists($function)
            && ! in_array(strtolower($function), $disabled, true);

        return [
            'exec'    => $enabled('exec'),
            'symlink' => $enabled('symlink'),
        ];
    }
}
