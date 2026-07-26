<?php

namespace App\Enums;

/**
 * Site-wide visual theme. Drives:
 *  - which component schema/view set the page builder shows
 *  - which Blade layout & section partials the frontend renders
 *  - which CSS/JS bundle is loaded.
 *
 * Persisted in the `settings` table under the `active_theme` key; resolved
 * everywhere via {@see activeTheme()} in app/helpers.php.
 */
enum Theme: string
{
    case Classic = 'classic';
    case Golden  = 'golden';

    public function label(): string
    {
        return match ($this) {
            self::Classic => __('Classic'),
            self::Golden  => __('Golden'),
        };
    }

    public function tagline(): string
    {
        return match ($this) {
            self::Classic => __('The original light, Bootstrap-driven look. Friendly, vivid, broad-audience.'),
            self::Golden  => __('Luxury private-banking aesthetic — obsidian + gold, serif display, Swiss-watch poise.'),
        };
    }

    public function previewImage(): string
    {
        return match ($this) {
            self::Classic => 'general/static/theme/classic-preview.svg',
            self::Golden  => 'general/static/theme/golden-preview.svg',
        };
    }

    public function accentColor(): string
    {
        return match ($this) {
            self::Classic => '#4663EE',
            self::Golden  => '#D4AF37',
        };
    }

    public function isDark(): bool
    {
        return $this === self::Golden;
    }

    /**
     * Editable landing-page color slots for this theme. The defaults below are
     * the single source of truth; saved overrides live in the settings table
     * under {@see colorSettingKey()} and are merged in by {@see resolvedPalette()}.
     *
     * @return array<int, array{key: string, label: string, hint: string, default: string}>
     */
    public function colorSlots(): array
    {
        return match ($this) {
            self::Classic => [
                ['key' => 'primary',   'label' => __('Primary'),   'hint' => __('Buttons, links & key accents'), 'default' => '#4663EE'],
                ['key' => 'secondary', 'label' => __('Secondary'), 'hint' => __('Secondary highlights & info'),   'default' => '#22BDFF'],
                ['key' => 'accent',    'label' => __('Accent'),    'hint' => __('Success & positive accents'),    'default' => '#17A86A'],
            ],
            self::Golden => [
                ['key' => 'gold',       'label' => __('Gold'),       'hint' => __('Primary metallic accent'),  'default' => '#D4AF37'],
                ['key' => 'gold_deep',  'label' => __('Deep Gold'),  'hint' => __('Gradient base & borders'),  'default' => '#B8860B'],
                ['key' => 'gold_light', 'label' => __('Light Gold'), 'hint' => __('Gradient highlight'),        'default' => '#F5E6A8'],
            ],
        };
    }

    /**
     * Settings key under which a given color slot's override is persisted.
     */
    public function colorSettingKey(string $slot): string
    {
        return "theme_{$this->value}_color_{$slot}";
    }

    /**
     * Resolve a single slot's color: a valid saved override, else the default.
     */
    public function colorValue(string $slot): string
    {
        $default = collect($this->colorSlots())->firstWhere('key', $slot)['default'] ?? '#000000';
        $stored  = strtoupper(trim((string) setting($this->colorSettingKey($slot), $default)));

        return preg_match('/^#[0-9A-F]{6}$/', $stored) === 1 ? $stored : strtoupper($default);
    }

    /**
     * The full resolved landing palette for this theme as slot => hex.
     *
     * @return array<string, string>
     */
    public function resolvedPalette(): array
    {
        return collect($this->colorSlots())
            ->mapWithKeys(fn (array $slot): array => [$slot['key'] => $this->colorValue($slot['key'])])
            ->all();
    }

    public static function default(): self
    {
        return self::Classic;
    }

    /**
     * Coerce a raw value (e.g. from settings) into a Theme, falling back to default.
     */
    public static function fromValueOrDefault(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) && ($theme = self::tryFrom($value))) {
            return $theme;
        }

        return self::default();
    }
}
