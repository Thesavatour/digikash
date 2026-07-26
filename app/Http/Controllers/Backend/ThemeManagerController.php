<?php

namespace App\Http\Controllers\Backend;

use App\Enums\Theme;
use App\Http\Requests\Backend\ThemeColorRequest;
use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Theme Manager — flips the site-wide `active_theme` setting between Classic
 * and Golden. The page builder and frontend layout both read this value via
 * {@see activeTheme()} so the entire surface follows in lock-step.
 */
class ThemeManagerController extends BaseController
{
    public static function permissions(): array
    {
        return [
            'index'                    => 'theme-manager-view',
            'activate'                 => 'theme-manager-update',
            'updateColors|resetColors' => 'theme-manager-update',
        ];
    }

    public function index(): View
    {
        $activeTheme = activeTheme();

        $themes = collect(Theme::cases())->map(function (Theme $theme) use ($activeTheme) {
            return [
                'value'           => $theme->value,
                'label'           => $theme->label(),
                'tagline'         => $theme->tagline(),
                'preview_image'   => $theme->previewImage(),
                'accent_color'    => $theme->accentColor(),
                'is_dark'         => $theme->isDark(),
                'is_active'       => $theme === $activeTheme,
                'component_count' => PageComponent::query()
                    ->forTheme($theme)
                    ->count(),
            ];
        });

        $palettes = collect(Theme::cases())->map(function (Theme $theme): array {
            return [
                'value'        => $theme->value,
                'label'        => $theme->label(),
                'accent_color' => $theme->accentColor(),
                'slots'        => collect($theme->colorSlots())
                    ->map(fn (array $slot): array => $slot + ['value' => $theme->colorValue($slot['key'])])
                    ->all(),
            ];
        });

        return view('backend.theme-manager.index', [
            'themes'      => $themes,
            'activeTheme' => $activeTheme,
            'palettes'    => $palettes,
        ]);
    }

    /**
     * Persist the landing-page color palette for a single theme.
     */
    public function updateColors(ThemeColorRequest $request, string $theme): RedirectResponse
    {
        $resolved = Theme::tryFrom($theme);

        if ($resolved === null) {
            notifyEvs('error', __('Unknown theme.'));

            return redirect()->route('admin.theme-manager.index');
        }

        $submitted = (array) $request->validated()['colors'];

        foreach ($resolved->colorSlots() as $slot) {
            $value = $submitted[$slot['key']] ?? null;

            if (is_string($value)) {
                setting([$resolved->colorSettingKey($slot['key']), strtoupper($value)]);
            }
        }

        notifyEvs('success', __(':theme landing colors saved.', ['theme' => $resolved->label()]));

        return redirect()->route('admin.theme-manager.index');
    }

    /**
     * Clear the saved palette for a theme, reverting it to the enum defaults.
     */
    public function resetColors(string $theme): RedirectResponse
    {
        $resolved = Theme::tryFrom($theme);

        if ($resolved === null) {
            notifyEvs('error', __('Unknown theme.'));

            return redirect()->route('admin.theme-manager.index');
        }

        foreach ($resolved->colorSlots() as $slot) {
            Setting::remove($resolved->colorSettingKey($slot['key']));
        }

        notifyEvs('success', __(':theme landing colors reset to defaults.', ['theme' => $resolved->label()]));

        return redirect()->route('admin.theme-manager.index');
    }

    public function activate(string $theme): RedirectResponse
    {
        $resolved = Theme::tryFrom($theme);

        if ($resolved === null) {
            notifyEvs('error', __('Unknown theme.'));

            return redirect()->route('admin.theme-manager.index');
        }

        if ($resolved === activeTheme()) {
            notifyEvs('info', __(':theme is already active.', ['theme' => $resolved->label()]));

            return redirect()->route('admin.theme-manager.index');
        }

        setting(['active_theme', $resolved->value]);

        // Page components / page slug caches are theme-sensitive — flush them
        // so the next request reflects the new pool of available blocks.
        $this->flushPageCaches();

        notifyEvs('success', __(':theme theme activated. Pages now render with this look.', [
            'theme' => $resolved->label(),
        ]));

        return redirect()->route('admin.theme-manager.index');
    }

    /**
     * Clear every cached Page/PageComponent payload so the new theme's
     * component pool & layout are picked up immediately.
     */
    protected function flushPageCaches(): void
    {
        $slugs   = DB::table('pages')->pluck('slug')->all();
        $pageIds = DB::table('pages')->pluck('id')->all();

        foreach ($slugs as $slug) {
            Cache::forget('page_slug_'.md5((string) $slug));
        }
        foreach ($pageIds as $id) {
            Cache::forget("page_components_{$id}");
        }
        Cache::forget('slugs_list');
    }
}
