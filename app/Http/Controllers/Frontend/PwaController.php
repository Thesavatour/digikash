<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PwaController extends Controller
{
    private const FALLBACK_THEME_COLOR = '#4663ee';

    private const FALLBACK_BACKGROUND = '#f3f7fb';

    private const FALLBACK_ICONS = [
        'icon_192'         => 'pwa/icons/icon-192.png',
        'icon_512'         => 'pwa/icons/icon-512.png',
        'maskable_icon'    => 'pwa/icons/maskable-512.png',
        'apple_touch_icon' => 'pwa/icons/apple-touch-icon.png',
    ];

    private const ICON_DIMENSIONS = [
        'icon_192'         => [192, 192],
        'icon_512'         => [512, 512],
        'maskable_icon'    => [512, 512],
        'apple_touch_icon' => [180, 180],
    ];

    /**
     * iOS Safari requests /apple-touch-icon.png at the site root when adding
     * to the Home Screen. Serving it here (short path, no query string)
     * avoids the letter-glyph fallback when the configured icon URL is long
     * or stored under /images/...
     */
    public function appleTouchIcon(): BinaryFileResponse|Response
    {
        if (! $this->isPwaEnabled()) {
            return response('Not Found', 404);
        }

        $path = $this->iconPath('apple_touch_icon');
        $absolute = $this->absoluteIconPath($path);

        if ($absolute === null || ! is_file($absolute)) {
            $fallback = public_path('pwa/icons/apple-touch-icon.png');
            if (! is_file($fallback)) {
                return response('Not Found', 404);
            }
            $absolute = $fallback;
        }

        $etag = '"'.sha1_file($absolute).'"';

        return response()->file($absolute, [
            'Content-Type'                   => 'image/png',
            // iOS probes this fixed root path even when HTML uses a hashed
            // /pwa/touch/... URL. Cloudflare was caching an older PNG for 7
            // days — force every CDN hop to skip storing this response.
            'Cache-Control'                  => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                         => 'no-cache',
            'Expires'                        => '0',
            'CDN-Cache-Control'              => 'no-store',
            'Cloudflare-CDN-Cache-Control'   => 'no-store',
            'Surrogate-Control'              => 'no-store',
            'X-Content-Type-Options'         => 'nosniff',
            'ETag'                           => $etag,
            'Last-Modified'                  => gmdate('D, d M Y H:i:s', (int) filemtime($absolute)).' GMT',
        ]);
    }

    public function manifest(): JsonResponse|Response
    {
        if (! $this->isPwaEnabled()) {
            return response('Not Found', 404);
        }

        $appName   = $this->appName();
        $shortName = $this->shortName();

        return response()
            ->json([
                'name'        => $appName,
                'short_name'  => $shortName,
                'description' => $this->description($appName),
                'id'          => '/dk-wallet-app',
                // start_url must return 2xx to Chrome's anonymous installability
                // probe; /user/dashboard would 302 → /user/login for that probe.
                // /launch is a public route that always returns 200 and the
                // inline script there bounces the real user to the dashboard.
                'start_url'      => '/launch?source=pwa',
                'scope'          => '/',
                'display'        => $this->display(),
                'orientation'    => $this->orientation(),
                'launch_handler' => [
                    'client_mode' => ['navigate-existing', 'auto'],
                ],
                'background_color'            => $this->backgroundColor(),
                'theme_color'                 => $this->themeColor(),
                'prefer_related_applications' => false,
                'icons'                       => $this->icons(),
            ], 200, [
                'Cache-Control'          => 'no-cache, must-revalidate',
                'Content-Type'           => 'application/manifest+json',
                'X-Content-Type-Options' => 'nosniff',
                'ETag'                   => '"'.$this->cacheVersion().'"',
            ], JSON_UNESCAPED_SLASHES);
    }

    public function serviceWorker(): Response
    {
        if (! $this->isPwaEnabled()) {
            return $this->disabledServiceWorker();
        }

        return response()
            ->view('pwa.service-worker', [
                'cacheVersion'       => $this->cacheVersion(),
                'offlineUrl'         => url('/offline'),
                'precacheUrls'       => $this->precacheUrls(),
                'staticPathPrefixes' => $this->staticPathPrefixes(),
                'sensitivePrefixes'  => $this->sensitivePrefixes(),
                'navigationScope'    => '/user/',
            ], 200, [
                'Cache-Control'          => 'no-cache, no-store, must-revalidate',
                'Content-Type'           => 'application/javascript; charset=UTF-8',
                'Service-Worker-Allowed' => '/',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function offline(): Response
    {
        if (! $this->isPwaEnabled()) {
            return response('Not Found', 404);
        }

        return response()
            ->view('pwa.offline', [
                'siteTitle'       => $this->appName(),
                'themeColor'      => $this->themeColor(),
                'backgroundColor' => $this->backgroundColor(),
                'iconUrl'         => $this->iconUrl('icon_192'),
                'offlineMessage'  => $this->offlineMessage(),
            ], 200, [
                'Cache-Control'          => 'public, max-age=600',
                'Content-Type'           => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    /**
     * Public PWA launcher — used as the manifest start_url so Chrome's
     * anonymous installability probe always sees a 200 OK. The inline
     * script in the view redirects the actual user on to /user/dashboard
     * once the PWA window has opened.
     */
    public function launcher(Request $request): Response
    {
        if (! $this->isPwaEnabled()) {
            return response('Not Found', 404);
        }

        if ($request->boolean('install')) {
            return $this->installBridge($request);
        }

        return response()
            ->view('pwa.launcher', [
                'siteTitle'         => $this->appName(),
                'themeColor'        => $this->themeColor(),
                'backgroundColor'   => $this->backgroundColor(),
                'foregroundColor'   => $this->onBackgroundTextColor(),
                'mutedColor'        => $this->onBackgroundMutedColor(),
                'spinnerTrackColor' => $this->onBackgroundSpinnerTrackColor(),
                'iconUrl'           => $this->iconUrl('icon_192'),
                'targetUrl'         => route('user.dashboard', [], false),
                'isLightBackground' => $this->isLightBackground(),
            ], 200, [
                'Cache-Control'          => 'public, max-age=300',
                'Content-Type'           => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function install(Request $request): Response
    {
        return $this->installBridge($request);
    }

    private function installBridge(Request $request): Response
    {
        if (! $this->isPwaEnabled()) {
            return response('Not Found', 404);
        }

        return response()
            ->view('pwa.install', [
                'siteTitle'       => $this->appName(),
                'themeColor'      => $this->themeColor(),
                'backgroundColor' => $this->backgroundColor(),
                'iconUrl'         => $this->iconUrl('icon_192'),
                'returnUrl'       => $this->safeReturnUrl((string) $request->query('return', route('user.dashboard', [], false))),
            ], 200, [
                'Cache-Control'          => 'public, max-age=300',
                'Content-Type'           => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    public function isPwaEnabled(): bool
    {
        return (bool) setting('pwa_enabled', true);
    }

    public function appName(): string
    {
        $custom    = trim((string) setting('pwa_app_name'));
        $siteTitle = trim((string) setting('site_title', config('app.name', 'DigiKash')));

        $name = $custom !== '' ? $custom : $siteTitle;

        return $name !== '' ? $name : 'DigiKash';
    }

    public function shortName(): string
    {
        $custom = trim((string) setting('pwa_short_name'));

        // Home-screen labels need a readable word. 1–2 character shorts like
        // "PP" cause iOS/Android to look "wrong" vs the App Name saved in admin.
        if ($custom !== '' && mb_strlen($custom) >= 3) {
            return Str::limit($custom, 12, '');
        }

        return Str::limit($this->appName(), 12, '');
    }

    public function themeColor(): string
    {
        $value = trim((string) setting('pwa_theme_color'));

        return $this->isValidHexColor($value) ? $value : self::FALLBACK_THEME_COLOR;
    }

    public function backgroundColor(): string
    {
        $value = trim((string) setting('pwa_background_color'));

        return $this->isValidHexColor($value) ? $value : self::FALLBACK_BACKGROUND;
    }

    public function isLightBackground(?string $hex = null): bool
    {
        $hex = ltrim((string) ($hex ?? $this->backgroundColor()), '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return true;
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Perceived luminance — treat mid/light backgrounds as light.
        $luminance = ((0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b)) / 255;

        return $luminance >= 0.58;
    }

    public function onBackgroundTextColor(?string $background = null): string
    {
        return $this->isLightBackground($background) ? '#152033' : '#ffffff';
    }

    public function onBackgroundMutedColor(?string $background = null): string
    {
        return $this->isLightBackground($background)
            ? 'rgba(21, 32, 51, 0.72)'
            : 'rgba(255, 255, 255, 0.86)';
    }

    public function onBackgroundSpinnerTrackColor(?string $background = null): string
    {
        return $this->isLightBackground($background)
            ? 'rgba(21, 32, 51, 0.18)'
            : 'rgba(255, 255, 255, 0.35)';
    }

    public function display(): string
    {
        $value   = (string) setting('pwa_display');
        $allowed = ['standalone', 'fullscreen', 'minimal-ui', 'browser'];

        return in_array($value, $allowed, true) ? $value : 'standalone';
    }

    public function orientation(): string
    {
        $value   = (string) setting('pwa_orientation');
        $allowed = ['any', 'portrait-primary', 'landscape-primary', 'portrait', 'landscape'];

        return in_array($value, $allowed, true) ? $value : 'portrait-primary';
    }

    public function iconUrl(string $key): string
    {
        $path = $this->iconPath($key);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // Root-relative URLs keep icons on the same host the phone is
        // browsing (LAN IP / tunnel / domain). Absolute APP_URL icons break
        // install when APP_URL doesn't match the request host — Chrome/iOS
        // then fall back to a letter glyph from the app name.
        $relative = '/'.ltrim($path, '/');
        $version = $this->iconVersionToken($path);

        return $version !== null ? $relative.'?v='.$version : $relative;
    }

    /**
     * Bust caches after admin updates PWA icons / branding colors.
     * Deletes prepared composites, republishes the iOS touch icon under a
     * content-hashed public path, and bumps the SW cache version tag.
     */
    public function bustIconCaches(?array $keys = null): void
    {
        $keys ??= array_keys(self::FALLBACK_ICONS);

        foreach ($keys as $key) {
            $this->clearPreparedIcons((string) $key);
        }

        Setting::add('pwa_cache_version', 'v'.now()->format('YmdHis'), 'string');
        $this->publishAppleTouchIcon();
    }

    /**
     * iOS ignores query-string cache busting on apple-touch-icon links and
     * freezes the home-screen glyph at install time. Publishing to a unique
     * content-hashed path forces Safari to fetch the new file when the user
     * re-adds the app.
     */
    public function appleTouchIconPublicUrl(): string
    {
        $published = trim((string) setting('pwa_apple_touch_published'));
        if ($published !== '' && is_file(public_path($published))) {
            return '/'.ltrim($published, '/');
        }

        return $this->publishAppleTouchIcon();
    }

    public function publishAppleTouchIcon(): string
    {
        $sourcePath = $this->iconPath('apple_touch_icon');
        $absolute   = $this->absoluteIconPath($sourcePath);

        if ($absolute === null || ! is_file($absolute)) {
            $fallback = public_path('pwa/icons/apple-touch-icon.png');
            if (! is_file($fallback)) {
                return '/apple-touch-icon.png';
            }
            $absolute = $fallback;
        }

        $hash     = substr((string) sha1_file($absolute), 0, 16);
        $relative = 'pwa/touch/apple-'.$hash.'.png';
        $destDir  = public_path('pwa/touch');
        $dest     = public_path($relative);

        if (! is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        if (! is_file($dest) || (string) sha1_file($dest) !== (string) sha1_file($absolute)) {
            @copy($absolute, $dest);
        }

        // Drop older published touch icons so public/ does not accumulate them.
        foreach (glob($destDir.'/apple-*.png') ?: [] as $old) {
            if (realpath($old) !== realpath($dest)) {
                @unlink($old);
            }
        }

        Setting::add('pwa_apple_touch_published', $relative, 'string');

        // Keep the fixed root filenames in sync too — iOS Safari still probes
        // /apple-touch-icon.png even when <link> points at the hashed path.
        foreach (['apple-touch-icon.png', 'apple-touch-icon-precomposed.png'] as $rootName) {
            $rootDest = public_path($rootName);
            // Prefer Laravel route over a static file that CDNs long-cache.
            // If a stale static copy exists, replace it with the current icon
            // then remove it so requests hit the no-store controller again.
            if (is_file($rootDest) || is_link($rootDest)) {
                @unlink($rootDest);
            }
        }

        return '/'.$relative;
    }

    private function clearPreparedIcons(string $key): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('pwa/prepared')) {
            return;
        }

        foreach ($disk->files('pwa/prepared') as $file) {
            if (str_starts_with(basename($file), $key.'-')) {
                $disk->delete($file);
            }
        }
    }

    private function iconPath(string $key): string
    {
        $fallback = self::FALLBACK_ICONS[$key] ?? '';
        $path     = trim((string) setting('pwa_'.$key));
        $path     = $path !== '' ? $path : $fallback;
        $path     = $this->browserIconPath($path);

        // Off-size uploads are rescaled by ensureDisplayableIcon() rather than
        // discarded. Requiring exact pixel dimensions here meant an admin
        // upload of any other size silently served the bundled default.
        if ($path !== '' && $this->isReadableIcon($path)) {
            return $this->ensureDisplayableIcon($key, $path);
        }

        $fallbackPath = $this->browserIconPath($fallback);

        return $fallbackPath !== ''
            ? $this->ensureDisplayableIcon($key, $fallbackPath)
            : $fallback;
    }

    /**
     * Normalise the icon to the size the manifest promises, and give it a
     * solid background when needed. White-on-transparent uploads are
     * invisible on light home-screen tiles, so iOS/Android show the first
     * letter of the app name instead.
     */
    private function ensureDisplayableIcon(string $key, string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL) || ! function_exists('imagecreatetruecolor')) {
            return $path;
        }

        $absolute = $this->absoluteIconPath($path);
        if ($absolute === null || ! is_file($absolute)) {
            return $path;
        }

        $dimensions = self::ICON_DIMENSIONS[$key] ?? [192, 192];
        $width      = (int) $dimensions[0];
        $height     = (int) $dimensions[1];

        $isMaskable = $key === 'maskable_icon';
        $sparseArt  = $this->iconNeedsBackgroundFill($absolute);

        // iOS paints transparent Home Screen pixels black and Android crops
        // maskable tiles to a circle, so both must end up opaque.
        $mustBeOpaque = $isMaskable || $key === 'apple_touch_icon';

        // Sparse or near-white art is inset on a solid tile so it stays
        // visible; anything else fills the canvas edge to edge.
        $inset       = $isMaskable || $sparseArt;
        $needsFill   = $inset || ($mustBeOpaque && $this->iconHasTransparency($absolute));
        $needsResize = ! $this->hasExactDimensions($absolute, $width, $height);

        if (! $needsFill && ! $needsResize) {
            return $path;
        }

        $bgHex     = $needsFill ? $this->backgroundColor() : null;
        $safeScale = $inset ? ($isMaskable ? 0.72 : 0.86) : 1.0;
        $contentHash = @sha1_file($absolute) ?: (string) filemtime($absolute);
        $fingerprint = substr(sha1($absolute.'|'.$contentHash.'|'.($bgHex ?? 'alpha').'|'.$width.'|'.$safeScale), 0, 16);
        $relative    = 'pwa/prepared/'.$key.'-'.$fingerprint.'.png';

        if (Storage::disk('public')->exists($relative)) {
            return 'storage/'.$relative;
        }

        $prepared = $this->rasterizeIcon($absolute, $width, $height, $bgHex, $safeScale);
        if ($prepared === null) {
            return $path;
        }

        Storage::disk('public')->makeDirectory('pwa/prepared');
        Storage::disk('public')->put($relative, $prepared);

        return 'storage/'.$relative;
    }

    private function iconNeedsBackgroundFill(string $absolutePath): bool
    {
        $image = @imagecreatefrompng($absolutePath);
        if ($image === false) {
            $image = @imagecreatefromstring((string) file_get_contents($absolutePath));
        }
        if ($image === false) {
            return false;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return false;
        }

        $samples = 0;
        $transparent = 0;
        $opaqueLight = 0;
        $opaqueDark = 0;
        $step = max(1, (int) floor(min($width, $height) / 48));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));
                $samples++;
                // GD alpha: 0 opaque … 127 fully transparent
                if (($color['alpha'] ?? 0) >= 110) {
                    $transparent++;

                    continue;
                }

                $luma = (($color['red'] ?? 0) + ($color['green'] ?? 0) + ($color['blue'] ?? 0)) / 3;
                if ($luma >= 210) {
                    $opaqueLight++;
                } else {
                    $opaqueDark++;
                }
            }
        }

        imagedestroy($image);

        if ($samples === 0) {
            return false;
        }

        $transparentRatio = $transparent / $samples;
        $lightAmongOpaque = ($opaqueLight + $opaqueDark) > 0
            ? $opaqueLight / ($opaqueLight + $opaqueDark)
            : 0;

        // Mostly empty canvas, or only near-white artwork on transparency.
        return $transparentRatio >= 0.45 || ($transparentRatio >= 0.20 && $lightAmongOpaque >= 0.85 && $opaqueDark < max(8, $samples * 0.02));
    }

    private function iconHasTransparency(string $absolutePath): bool
    {
        $image = @imagecreatefrompng($absolutePath);
        if ($image === false) {
            $image = @imagecreatefromstring((string) file_get_contents($absolutePath));
        }
        if ($image === false) {
            return false;
        }

        $width  = imagesx($image);
        $height = imagesy($image);
        $step   = max(1, (int) floor(min($width, $height) / 48));

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                // GD alpha: 0 opaque … 127 fully transparent.
                if ((imagecolorsforindex($image, imagecolorat($image, $x, $y))['alpha'] ?? 0) >= 8) {
                    imagedestroy($image);

                    return true;
                }
            }
        }

        imagedestroy($image);

        return false;
    }

    /**
     * Redraw the source image at exactly $width x $height. A null
     * $backgroundHex keeps the canvas transparent.
     */
    private function rasterizeIcon(
        string $sourcePath,
        int $width,
        int $height,
        ?string $backgroundHex,
        float $safeScale
    ): ?string {
        $source = @imagecreatefrompng($sourcePath);
        if ($source === false) {
            $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
        }
        if ($source === false) {
            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        if ($backgroundHex === null) {
            // Blending stays off so imagecopyresampled() copies alpha verbatim
            // instead of compositing the artwork onto opaque black.
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        } else {
            imagealphablending($canvas, true);
            imagesavealpha($canvas, false);

            [$r, $g, $b] = $this->hexToRgb($backgroundHex);
            imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, $r, $g, $b));
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $targetW = max(1, (int) round($width * $safeScale));
        $targetH = max(1, (int) round($height * $safeScale));
        $scale = min($targetW / max(1, $srcW), $targetH / max(1, $srcH));
        $drawW = max(1, (int) round($srcW * $scale));
        $drawH = max(1, (int) round($srcH * $scale));
        $dstX = (int) floor(($width - $drawW) / 2);
        $dstY = (int) floor(($height - $drawH) / 2);

        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $drawW, $drawH, $srcW, $srcH);

        ob_start();
        imagepng($canvas, null, 6);
        $binary = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [243, 247, 251];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function iconVersion(string $path): ?int
    {
        $absolute = $this->absoluteIconPath($path);

        return $absolute !== null && is_file($absolute) ? (int) filemtime($absolute) : null;
    }

    private function iconVersionToken(string $path): ?string
    {
        $absolute = $this->absoluteIconPath($path);
        if ($absolute === null || ! is_file($absolute)) {
            return null;
        }

        $hash = @sha1_file($absolute) ?: (string) filemtime($absolute);

        return substr($hash, 0, 12);
    }

    private function browserIconPath(string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (is_file(public_path($path))) {
            return $path;
        }

        $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return Storage::disk('public')->exists($storagePath) ? 'storage/'.$storagePath : $path;
    }

    private function isReadableIcon(string $path): bool
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return true;
        }

        $absolutePath = $this->absoluteIconPath($path);

        return $absolutePath !== null
            && is_file($absolutePath)
            && is_array(@getimagesize($absolutePath));
    }

    private function hasExactDimensions(string $absolutePath, int $width, int $height): bool
    {
        $imageSize = @getimagesize($absolutePath);

        return is_array($imageSize)
            && (int) $imageSize[0] === $width
            && (int) $imageSize[1] === $height;
    }

    private function absoluteIconPath(string $path): ?string
    {
        $path = ltrim($path, '/');

        if (is_file(public_path($path))) {
            return public_path($path);
        }

        $storagePath = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;

        return Storage::disk('public')->exists($storagePath)
            ? Storage::disk('public')->path($storagePath)
            : null;
    }

    private function description(string $appName): string
    {
        $custom = trim((string) setting('pwa_description'));

        return $custom !== '' ? $custom : 'Secure mobile wallet dashboard for '.$appName.'.';
    }

    private function offlineMessage(): string
    {
        $custom = trim((string) setting('pwa_offline_message'));

        return $custom !== ''
            ? $custom
            : 'A live connection is required for balances, payments, and transactions. Please reconnect and try again.';
    }

    private function cacheVersion(): string
    {
        $appVersion          = (string) config('app.version', '1');
        $manualTag           = trim((string) setting('pwa_cache_version'));
        $assetMtime          = $this->latestAssetMtime();
        $settingsFingerprint = substr(sha1((string) json_encode($this->cacheSettingFingerprint(), JSON_UNESCAPED_SLASHES)), 0, 12);

        $combined = $appVersion.'-'.$assetMtime.'-'.$settingsFingerprint.($manualTag !== '' ? '-'.$manualTag : '');

        return preg_replace('/[^A-Za-z0-9_.-]/', '-', $combined) ?: '1';
    }

    /**
     * Public wrapper for Blade / meta cache busting.
     */
    public function cacheVersionPublic(): string
    {
        return $this->cacheVersion();
    }

    /**
     * @return array<string, string>
     */
    private function cacheSettingFingerprint(): array
    {
        return [
            'app_name'         => $this->appName(),
            'short_name'       => $this->shortName(),
            'description'      => $this->description($this->appName()),
            'theme_color'      => $this->themeColor(),
            'background_color' => $this->backgroundColor(),
            'display'          => $this->display(),
            'orientation'      => $this->orientation(),
            'icon_192'         => $this->iconPath('icon_192'),
            'icon_512'         => $this->iconPath('icon_512'),
            'maskable_icon'    => $this->iconPath('maskable_icon'),
            'apple_touch_icon' => $this->iconPath('apple_touch_icon'),
            'offline_message'  => $this->offlineMessage(),
        ];
    }

    private function latestAssetMtime(): int
    {
        $candidates = [
            'general/css/common.css',
            'general/js/helpers.js',
            'frontend/js/pwa.js',
            'frontend/js/dashboard-mobile-app.js',
            'frontend/css/dashboard-mobile-app.css',
            'frontend/css/dashboard-style.css',
        ];

        $latest = 0;
        foreach ($candidates as $relative) {
            $absolute = public_path($relative);
            if (is_file($absolute)) {
                $latest = max($latest, (int) filemtime($absolute));
            }
        }

        return $latest > 0 ? $latest : time();
    }

    private function isValidHexColor(string $value): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $value);
    }

    private function safeReturnUrl(string $url): string
    {
        $fallback = route('user.dashboard', [], false);
        $url      = trim($url);

        if ($url === '' || str_starts_with($url, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
            return $fallback;
        }

        return str_starts_with($url, '/') ? $url : $fallback;
    }

    private function disabledServiceWorker(): Response
    {
        $script = <<<'JS'
self.addEventListener("install", function () { self.skipWaiting(); });
self.addEventListener("activate", function (event) {
    event.waitUntil((async function () {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter(function (key) { return key.indexOf("digikash-pwa") === 0; })
            .map(function (key) { return caches.delete(key); }));
        if (self.registration && self.registration.unregister) {
            await self.registration.unregister();
        }
        const clients = await self.clients.matchAll({ type: "window" });
        clients.forEach(function (client) {
            try { client.navigate(client.url); } catch (e) {}
        });
    })());
});
JS;

        return response($script, 200, [
            'Cache-Control'          => 'no-cache, no-store, must-revalidate',
            'Content-Type'           => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<int, array{src: string, sizes: string, type: string, purpose?: string}>
     */
    private function icons(): array
    {
        return [
            [
                'src'     => $this->iconUrl('icon_192'),
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => $this->iconUrl('icon_512'),
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src'     => $this->iconUrl('maskable_icon'),
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function precacheUrls(): array
    {
        return [
            url('/offline'),
            $this->iconUrl('icon_192'),
            $this->iconUrl('icon_512'),
            $this->iconUrl('maskable_icon'),
            $this->iconUrl('apple_touch_icon'),
            asset('general/css/bootstrap.min.css'),
            asset('general/css/fontawesome.min.css'),
            asset('general/css/simple-notify.min.css'),
            asset('general/css/common.css?v='.$this->publicFileVersion('general/css/common.css')),
            asset('general/css/daterangepicker.css'),
            asset('frontend/css/_variables.css?v='.config('app.version')),
            asset('frontend/css/dashboard-style.css?v='.config('app.version')),
            asset('frontend/css/dashboard-responsive.css?v='.config('app.version')),
            asset('frontend/css/premium-header.css?v='.config('app.version')),
            asset('frontend/css/dashboard-mobile-app.css?v='.$this->publicFileVersion('frontend/css/dashboard-mobile-app.css')),
            asset('frontend/js/jquery-3.7.1.min.js'),
            asset('general/js/bootstrap.bundle.min.js'),
            asset('general/js/simple-notify.min.js'),
            asset('general/js/helpers.js?v='.$this->publicFileVersion('general/js/helpers.js')),
            asset('frontend/js/dashboard-main.js'),
            asset('frontend/js/dashboard-mobile-app.js?v='.$this->publicFileVersion('frontend/js/dashboard-mobile-app.js')),
            asset('frontend/js/pwa.js?v='.$this->publicFileVersion('frontend/js/pwa.js')),
        ];
    }

    private function publicFileVersion(string $path): string
    {
        $publicPath = public_path($path);

        return config('app.version').'-'.(is_file($publicPath) ? filemtime($publicPath) : '1');
    }

    /**
     * @return array<int, string>
     */
    private function staticPathPrefixes(): array
    {
        return [
            '/frontend/css/',
            '/frontend/js/',
            '/general/css/',
            '/general/js/',
            '/general/static/',
            '/general/webfonts/',
            '/pwa/',
            '/storage/pwa/',
            '/images/',
            '/storage/images/',
            '/apple-touch-icon.png',
            '/apple-touch-icon-precomposed.png',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sensitivePrefixes(): array
    {
        return [
            '/admin',
            '/api',
            '/currency-rate',
            '/file/download',
            '/ipn',
            '/payment',
            '/payment-link',
            '/summernote',
            '/user/notifications/recent',
            '/user/wallet/currency-info',
            '/user/wallet/info',
            '/user/wallet/supported-payment-methods',
            '/user/wallet/validate-recipient',
            '/webhooks',
        ];
    }
}
