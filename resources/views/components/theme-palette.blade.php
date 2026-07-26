@php
    /**
     * Landing-page color overrides for the *active* theme. Admins pick these in
     * Theme Manager (/admin/theme-manager); they are stored per theme/slot in
     * settings and resolved by {@see App\Enums\Theme::resolvedPalette()}.
     *
     * Scoped to the marketing/landing heads only — dashboards keep their own
     * role-branding palette. Mirrors components/role-branding-theme.blade.php.
     */
    $theme   = activeTheme();
    $palette = $theme->resolvedPalette();

    $rgb = static function (string $hex): string {
        $hex = ltrim($hex, '#');

        return collect(str_split($hex, 2))
            ->map(fn (string $value): int => hexdec($value))
            ->implode(', ');
    };

    $shade = static function (string $hex, int $percent): string {
        $hex      = ltrim($hex, '#');
        $channels = collect(str_split($hex, 2))->map(fn (string $value): int => hexdec($value));
        $target   = $percent < 0 ? 0 : 255;
        $factor   = abs($percent) / 100;

        return '#'.$channels
            ->map(fn (int $channel): string => str_pad(dechex((int) round($channel + (($target - $channel) * $factor))), 2, '0', STR_PAD_LEFT))
            ->implode('');
    };
@endphp

<style data-theme-palette="{{ $theme->value }}">
    @if($theme === \App\Enums\Theme::Golden)
        :root {
            --gold: {{ $palette['gold'] }};
            --gold-deep: {{ $palette['gold_deep'] }};
            --gold-light: {{ $palette['gold_light'] }};
            --gold-grad: linear-gradient(135deg, {{ $palette['gold_light'] }} 0%, {{ $palette['gold'] }} 45%, {{ $palette['gold_deep'] }} 100%);
            --gold-grad-soft: linear-gradient(135deg, rgba({{ $rgb($palette['gold_light']) }}, .18) 0%, rgba({{ $rgb($palette['gold']) }}, .22) 45%, rgba({{ $rgb($palette['gold_deep']) }}, .18) 100%);
        }
    @else
        :root,
        [data-theme="light"] {
            --front-color-primary: {{ $palette['primary'] }};
            --front-color-primary-rgb: {{ $rgb($palette['primary']) }};
            --front-color-primary-hover: {{ strtoupper($shade($palette['primary'], -12)) }};
            --front-color-primary-dark: {{ strtoupper($shade($palette['primary'], -25)) }};
            --front-color-secondary: {{ $palette['secondary'] }};
            --front-color-accent: {{ $palette['accent'] }};
        }
    @endif
</style>
