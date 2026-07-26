@php
    $pwa = app(\App\Http\Controllers\Frontend\PwaController::class);
@endphp
@if($pwa->isPwaEnabled())
    <meta name="theme-color" content="{{ $pwa->themeColor() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    {{-- iOS Home Screen label. Prefer shortName() (falls back to App Name). --}}
    <meta name="apple-mobile-web-app-title" content="{{ $pwa->shortName() }}">
    <meta name="application-name" content="{{ $pwa->appName() }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    {{-- iOS prefers a root /apple-touch-icon.png (no query string). Keep the
         sized link as a secondary source for browsers that honor sizes=. --}}
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $pwa->iconUrl('apple_touch_icon') }}">
    <link rel="apple-touch-icon-precomposed" href="/apple-touch-icon.png">
    {{-- 192/512 PWA icons are intentionally NOT declared as <link rel="icon">.
         Chrome/Android pull those from the manifest's icons array for install
         and the home screen — duplicating them here as <link rel="icon"> just
         makes the browser pick them for the tab favicon and ignore the
         configured site_favicon. The browser tab keeps using the
         site_favicon declared in the layout's _head.blade.php. --}}
@endif
