@php
    $pwa = app(\App\Http\Controllers\Frontend\PwaController::class);
    $appleTouchVersion = preg_replace('/[^A-Za-z0-9]/', '', (string) setting('pwa_cache_version', '')).'-'.substr(sha1($pwa->iconUrl('apple_touch_icon')), 0, 10);
@endphp
@if($pwa->isPwaEnabled())
    <meta name="theme-color" content="{{ $pwa->themeColor() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    {{-- iOS Home Screen label. Prefer shortName() (falls back to App Name). --}}
    <meta name="apple-mobile-web-app-title" content="{{ $pwa->shortName() }}">
    <meta name="application-name" content="{{ $pwa->appName() }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}?v={{ urlencode($pwa->cacheVersionPublic()) }}">
    {{-- Version query helps browsers that honor it; root path still served for iOS. --}}
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $appleTouchVersion }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $pwa->iconUrl('apple_touch_icon') }}">
    <link rel="apple-touch-icon-precomposed" href="/apple-touch-icon-precomposed.png?v={{ $appleTouchVersion }}">
    {{-- 192/512 PWA icons are intentionally NOT declared as <link rel="icon">.
         Chrome/Android pull those from the manifest's icons array for install
         and the home screen — duplicating them here as <link rel="icon"> just
         makes the browser pick them for the tab favicon and ignore the
         configured site_favicon. The browser tab keeps using the
         site_favicon declared in the layout's _head.blade.php. --}}
@endif
