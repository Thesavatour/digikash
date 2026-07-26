<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $themeColor }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $shortName ?? $siteTitle }}">
    <meta name="robots" content="noindex,nofollow">
    <link rel="apple-touch-icon" href="{{ $appleTouchIconUrl ?? '/apple-touch-icon.png' }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $appleTouchIconUrl ?? '/apple-touch-icon.png' }}">
    <title>{{ $siteTitle }}</title>
    <style>
        :root { color-scheme: {{ $isLightBackground ? 'light' : 'dark' }}; }
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            background: {{ $backgroundColor }};
            color: {{ $foregroundColor }};
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
            text-align: center;
        }
        .dk-launcher {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100%;
            padding: 24px;
            gap: 18px;
        }
        .dk-launcher__title {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0;
            margin: 0;
            color: {{ $foregroundColor }};
        }
        .dk-launcher__hint {
            margin: 0;
            font-size: 14px;
            color: {{ $mutedColor }};
        }
        .dk-launcher__spinner {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 3px solid {{ $spinnerTrackColor }};
            border-top-color: {{ $themeColor }};
            animation: dk-spin 0.9s linear infinite;
        }
        .dk-launcher__icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            object-fit: cover;
            background: {{ $isLightBackground ? '#ffffff' : 'rgba(255,255,255,0.08)' }};
            border: 1px solid {{ $isLightBackground ? 'rgba(21, 32, 51, 0.08)' : 'rgba(255, 255, 255, 0.16)' }};
            box-shadow: 0 18px 40px {{ $isLightBackground ? 'rgba(15, 23, 42, 0.12)' : 'rgba(0, 0, 0, 0.28)' }};
        }
        .dk-launcher__continue {
            color: {{ $themeColor }};
            font-weight: 600;
        }
        @keyframes dk-spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <main class="dk-launcher" role="status" aria-live="polite">
        <img class="dk-launcher__icon" src="{{ $iconUrl }}" alt="" aria-hidden="true" loading="eager">
        <div class="dk-launcher__spinner" aria-hidden="true"></div>
        <h1 class="dk-launcher__title">{{ $siteTitle }}</h1>
        <p class="dk-launcher__hint">{{ __('Opening your wallet…') }}</p>
        <noscript>
            <p><a class="dk-launcher__continue" href="{{ $targetUrl }}">{{ __('Tap here to continue') }}</a></p>
        </noscript>
    </main>
    <script>
    'use strict';
        (function () {
            var target = {!! json_encode($targetUrl, JSON_UNESCAPED_SLASHES) !!};
            try {
                window.location.replace(target);
            } catch (error) {
                window.location.href = target;
            }
        })();
    </script>
</body>
</html>
