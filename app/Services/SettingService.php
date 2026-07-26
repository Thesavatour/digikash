<?php

namespace App\Services;

use App\Http\Controllers\Frontend\PwaController;
use App\Models\Feature;
use App\Models\Setting;
use App\Traits\FileManageTrait;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SettingService
{
    use FileManageTrait;
    use ValidatesRequests;

    /**
     * Update the settings for a given section.
     *
     * @param string  $section The section of the settings.
     * @param Request $request The request object.
     */
    public function update(string $section, Request $request): void
    {
        $rules = Setting::getValidationRules($section);
        $data  = method_exists($request, 'validated')
            ? $request->validated()
            : $this->validate(
                $request,
                $rules,
                Setting::getValidationMessages($section),
                Setting::getValidationAttributes($section)
            );

        // Get the valid settings keys.
        $validSettings = array_keys($rules);

        $pwaIconKeys = [
            'pwa_icon_192'         => 'icon_192',
            'pwa_icon_512'         => 'icon_512',
            'pwa_maskable_icon'    => 'maskable_icon',
            'pwa_apple_touch_icon' => 'apple_touch_icon',
        ];
        $touchedPwaIconKeys = [];
        $oldPwaBackground = $section === 'pwa_settings'
            ? (string) Setting::get('pwa_background_color')
            : null;

        // Update the settings.
        foreach ($data as $key => $val) {
            // Check if the key is a valid setting.
            if (in_array($key, $validSettings, true)) {

                // Check if the request has a file for the key.
                if ($request->hasFile($key)) {
                    // Get the old image for the key.
                    $oldImage = Setting::get($key, $section);

                    // Upload the new image and get the path.
                    $val = self::uploadImage($val, $oldImage);

                    if (isset($pwaIconKeys[$key])) {
                        $touchedPwaIconKeys[] = $pwaIconKeys[$key];
                    }
                }

                // Add the setting.
                Setting::add($key, $val, Setting::getDataType($key, $section));
            }
        }

        // Section-specific post-processing — sync agent_program_enabled
        // into the FeatureManager so the existing feature.enabled middleware,
        // sidebar feature_key check, and admin Features UI all reflect the
        // master toggle without duplicating logic.
        if ($section === 'agent_settings') {
            $this->syncAgentProgramFlag();
        }

        if ($section === 'pwa_settings') {
            $backgroundChanged = $oldPwaBackground !== null
                && array_key_exists('pwa_background_color', $data)
                && (string) $data['pwa_background_color'] !== $oldPwaBackground;

            $this->refreshPwaIconCaches($touchedPwaIconKeys, $backgroundChanged);
        }
    }

    /**
     * Clear prepared PWA icon composites and bump the SW cache tag so
     * installed apps pick up new icons on the next visit.
     *
     * @param  list<string>  $touchedIconKeys
     */
    protected function refreshPwaIconCaches(array $touchedIconKeys, bool $backgroundChanged = false): void
    {
        if ($touchedIconKeys === [] && ! $backgroundChanged) {
            return;
        }

        try {
            app(PwaController::class)->bustIconCaches(
                $touchedIconKeys !== [] ? $touchedIconKeys : null
            );
        } catch (\Throwable) {
            // Never block settings save if icon cache cleanup fails.
            Setting::add('pwa_cache_version', 'v'.now()->format('YmdHis'), 'string');
        }
    }

    /**
     * Mirror the `agent_program_enabled` setting onto the FeatureManager
     * `agent_program` feature row so legacy gates pick it up too.
     */
    protected function syncAgentProgramFlag(): void
    {
        if (! Schema::hasTable('features')) {
            return;
        }

        $enabled = (bool) (Setting::get('agent_program_enabled') ?? true);

        Feature::query()
            ->where('key', 'agent_program')
            ->update(['is_enabled' => $enabled]);

        // Flush the cached catalog so the next request sees fresh state.
        if (app()->bound(FeatureManager::class)) {
            app(FeatureManager::class)->flush();
        }
    }
}
