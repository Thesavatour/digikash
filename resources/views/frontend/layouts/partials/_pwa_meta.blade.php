@php
    $pwa = app(\App\Http\Controllers\Frontend\PwaController::class);
    // Content-hashed path (not ?v=). iOS Safari ignores query strings on
    // apple-touch-icon and keeps the home-screen glyph until the user
    // deletes + re-adds the app with a new href path.
    $appleTouchHref = $pwa->appleTouchIconPublicUrl();
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
    <link rel="apple-touch-icon" href="{{ $appleTouchHref }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $appleTouchHref }}">
    <link rel="apple-touch-icon-precomposed" href="{{ $appleTouchHref }}">
    {{-- 192/512 PWA icons are intentionally NOT declared as <link rel="icon">.
         Chrome/Android pull those from the manifest's icons array for install
         and the home screen — duplicating them here as <link rel="icon"> just
         makes the browser pick them for the tab favicon and ignore the
         configured site_favicon. The browser tab keeps using the
         site_favicon declared in the layout's _head.blade.php. --}}
@endif
