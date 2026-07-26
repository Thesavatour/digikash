@extends('backend.layouts.app')

@section('title', __('Project Updater'))

@push('styles')
    @php
        $puCssVersion = config('app.version').'-'.filemtime(public_path('backend/css/project-updater.css'));
    @endphp
    <link rel="stylesheet" href="{{ asset('backend/css/project-updater.css?v='.$puCssVersion) }}">
@endpush

@section('content')
    @php
        $licenseActive       = $license?->isActive() ?? false;
        $hasUpdate           = $latest && $latest->status === 'available';
        $isCurrent           = $latest && $latest->status === 'current';
        $justInstalled       = session('updater_just_installed');
        $lastInstallFailed   = $latest && $latest->status === 'failed';
        $checksTotal         = count($checks ?? []);
        $checksPassed        = collect($checks ?? [])->where('status', true)->count();
        $checksReady         = $checksTotal > 0 && $checksPassed === $checksTotal;

        if ($justInstalled) {
            $initialState = 'install-success';
        } elseif ($lastInstallFailed) {
            $initialState = 'install-failed';
        } elseif (! $license) {
            $initialState = 'idle-nolicense';
        } elseif ($hasUpdate) {
            $initialState = 'update-available';
        } else {
            $initialState = 'license-active';
        }

        $supportTone          = $license?->supportTone() ?? 'secondary';
        $supportDaysRemaining = $license?->supportDaysRemaining();
        $supportExpired       = $license && $supportDaysRemaining !== null && $supportDaysRemaining < 0;
        $supportEndingSoon    = $license?->supportExpiresSoon() ?? false;

        $supportRemainingLabel = $license
            ? ($supportExpired
                ? __(':days days ago', ['days' => abs((int) $supportDaysRemaining)])
                : ($supportDaysRemaining !== null
                    ? __(':days days left', ['days' => (int) $supportDaysRemaining])
                    : __('Unknown')))
            : __('Not activated');

        $supportKpiDot = match (true) {
            ! $license                 => 'muted',
            $supportExpired            => 'danger',
            $supportEndingSoon         => 'warning',
            default                    => 'success',
        };

        $releaseKpiDot = match (true) {
            $hasUpdate                                  => 'warning',
            $isCurrent                                  => 'success',
            $latest && $latest->status === 'installed' => 'success',
            $latest && $latest->status === 'failed'    => 'danger',
            default                                     => 'muted',
        };

        $checksKpiDot = $checksReady ? 'success' : 'warning';

        $changelog = collect($latest?->changelog ?? [])
            ->map(function ($entry) {
                if (is_array($entry)) {
                    return [
                        'tag'  => $entry['tag'] ?? 'changed',
                        'text' => $entry['text'] ?? ($entry['title'] ?? json_encode($entry)),
                    ];
                }

                return ['tag' => 'changed', 'text' => (string) $entry];
            });

        $heroStatusText = match ($initialState) {
            'idle-nolicense'   => __('Action required: activate your license to enable updates'),
            'license-active'   => __('All systems ready'),
            'update-available' => __('Action required: version :v is available to install', ['v' => $latest?->version]),
            'install-success'  => __('You are now on :v · install completed successfully', ['v' => $justInstalled ?: $latest?->version]),
            'install-failed'   => __('Last install attempt failed — recovery available'),
            default            => __('All systems ready'),
        };

        // NOTE: 'install-failed' was previously listed in BOTH the warning arm
        // AND the danger arm — but match() takes the FIRST matching arm so the
        // danger mapping was unreachable. Failed installs now correctly render
        // with the danger tone (red), not warning (amber).
        $heroStatusTone = match ($initialState) {
            'install-failed'                       => 'danger',
            'idle-nolicense', 'update-available'   => 'warning',
            default                                => 'success',
        };
    @endphp

    <div class="project-updater" data-state="{{ $initialState }}">

        {{-- ── Hero ──────────────────────────────────────────────────── --}}
        <header class="pu-hero"
            data-show="idle-nolicense license-active update-available install-success install-failed">
            <div class="pu-hero__main">
                <div class="pu-hero__eyebrow">{{ __('App management') }}</div>
                <h1 class="pu-hero__title">{{ __('Project Updater') }}</h1>
                <div class="pu-hero__status {{ $heroStatusTone === 'warning' ? 'pu-hero__status--warning' : ($heroStatusTone === 'danger' ? 'pu-hero__status--danger' : '') }}"
                    id="pu-hero-status">
                    <span class="pu-hero__status-dot"></span>
                    <span class="pu-hero__status-text">{{ $heroStatusText }}</span>
                </div>
            </div>
            <div class="pu-hero__aside">
                <div class="pu-version">
                    <span class="pu-version__label">{{ __('Current') }}</span>
                    <span class="pu-version__value">v{{ config('app.version') }}</span>
                </div>
                @can('project-updater-view')
                    <form action="{{ route('admin.app.updater.check') }}" method="POST"
                        data-show="idle-nolicense license-active install-success install-failed">
                        @csrf
                        <button type="submit" class="pu-btn pu-btn--secondary" @disabled(! $licenseActive)>
                            <i class="fas fa-rotate"></i> {{ __('Check for updates') }}
                        </button>
                    </form>
                @endcan
                @can('project-updater-manage')
                    <button type="button" class="pu-btn pu-btn--primary"
                        data-action="open-install-modal"
                        data-show="update-available">
                        <i class="fas fa-download"></i> {{ __('Install update') }}
                    </button>
                @endcan
                <button type="button" class="pu-btn pu-btn--ghost pu-btn--sm"
                    data-action="open-help-modal"
                    title="{{ __('Server troubleshooting guide') }}">
                    <i class="fas fa-life-ring"></i> {{ __('Server help') }}
                </button>
            </div>
        </header>

        {{-- ── Success / Failure banners ─────────────────────────────── --}}
        <div class="pu-banner pu-banner--success" data-show="install-success">
            <div class="pu-banner__icon"><i class="fas fa-check"></i></div>
            <div style="flex:1; min-width:0;">
                <div class="pu-banner__title">{{ __('Update installed successfully') }}</div>
                <div class="pu-banner__sub">
                    {{ __('Digikash is now running version') }}
                    <span class="pu-mono">{{ $justInstalled ?: $latest?->version }}</span>.
                </div>
            </div>
        </div>

        <div class="pu-banner pu-banner--danger" data-show="install-failed">
            <div class="pu-banner__icon"><i class="fas fa-xmark"></i></div>
            <div style="flex:1; min-width:0;">
                <div class="pu-banner__title">{{ __('Update failed') }}</div>
                <div class="pu-banner__sub">
                    {{ $latest?->error_message ?: __('The last install attempt could not complete. Recovery available below.') }}
                    @if(! empty($failureCategory))
                        <button type="button"
                            class="pu-link"
                            style="background:none; border:0; padding:0; margin-left:6px; font-size:inherit;"
                            data-action="open-help-modal"
                            data-open-category="{{ $failureCategory }}">
                            <i class="fas fa-life-ring" style="margin-right:4px;"></i>{{ __('View troubleshooting →') }}
                        </button>
                    @endif
                </div>
            </div>
            @can('project-updater-manage')
                <button type="button" class="pu-btn pu-btn--secondary" data-action="open-install-modal">
                    <i class="fas fa-rotate-right"></i> {{ __('Retry install') }}
                </button>
            @endcan
        </div>

        {{-- ── KPI Strip ─────────────────────────────────────────────── --}}
        <div class="pu-kpi-grid" data-show="idle-nolicense license-active update-available install-success install-failed"
            aria-label="{{ __('Updater status overview') }}">
            <div class="pu-kpi">
                <div class="pu-kpi__label">{{ __('License') }}</div>
                <div class="pu-kpi__value">
                    <span class="pu-kpi__dot pu-kpi__dot--{{ $licenseActive ? 'success' : ($license ? 'warning' : 'muted') }}"></span>
                    <span>{{ $licenseActive ? __('Active') : ($license ? __('Inactive') : __('Not linked')) }}</span>
                </div>
                <div class="pu-kpi__sub">
                    {{ $license
                        ? ($license->buyer_username ? 'Buyer · ' . $license->buyer_username : ($license->domain ?: request()->getHost()))
                        : __('Activate to enable updates') }}
                </div>
            </div>

            <div class="pu-kpi">
                <div class="pu-kpi__label">{{ __('Envato support') }}</div>
                <div class="pu-kpi__value">
                    <span class="pu-kpi__dot pu-kpi__dot--{{ $supportKpiDot }}"></span>
                    <span>{{ $supportRemainingLabel }}</span>
                </div>
                <div class="pu-kpi__sub">
                    {{ $license
                        ? ($license->support_until ? __('Until') . ' ' . $license->support_until->format('M d, Y') : __('Unknown'))
                        : __('Activate license to check') }}
                </div>
            </div>

            <div class="pu-kpi">
                <div class="pu-kpi__label">{{ __('Latest release') }}</div>
                <div class="pu-kpi__value">
                    <span class="pu-kpi__dot pu-kpi__dot--{{ $releaseKpiDot }}"></span>
                    <span class="pu-mono">{{ $latest?->version ? 'v' . $latest->version : '—' }}</span>
                </div>
                <div class="pu-kpi__sub">
                    @if($hasUpdate)
                        {{ __('Released :date · :channel', ['date' => $latest?->release_date?->format('M d, Y') ?? '—', 'channel' => $latest?->channel ?? config('project_updater.channel')]) }}
                    @elseif($isCurrent)
                        {{ __("You're on the latest stable") }}
                    @else
                        {{ __('Run a check to discover releases') }}
                    @endif
                </div>
            </div>

            <div class="pu-kpi">
                <div class="pu-kpi__label">{{ __('Readiness') }}</div>
                <div class="pu-kpi__value">
                    <span class="pu-kpi__dot pu-kpi__dot--{{ $checksKpiDot }}"></span>
                    <span>{{ $checksPassed }} / {{ $checksTotal }} {{ __('passing') }}</span>
                </div>
                <div class="pu-kpi__sub">
                    {{ $checksReady ? __('Ready to install') : __('Resolve highlighted items') }}
                </div>
            </div>
        </div>

        {{-- ── No-license activation alert ────────────────────────────── --}}
        <div class="pu-alert pu-alert--primary" data-show="idle-nolicense">
            <div class="pu-alert__icon"><i class="fas fa-key"></i></div>
            <div class="pu-alert__body">
                <div class="pu-alert__title">{{ __('Activate your Digikash license to enable updates') }}</div>
                <div class="pu-alert__text">
                    {{ __('Enter your 36-character Envato purchase code below. We will bind it to') }}
                    <span class="pu-mono">{{ request()->getHost() }}</span>
                    {{ __('and unlock update checks.') }}
                </div>
            </div>
        </div>

        {{-- ── Support-expiring alert (license-active states only) ────── --}}
        @if($license && ($supportExpired || $supportEndingSoon))
            <div class="pu-alert pu-alert--{{ $supportExpired ? 'danger' : 'warning' }}"
                data-show="license-active update-available install-success install-failed">
                <div class="pu-alert__icon"><i class="fas {{ $supportExpired ? 'fa-exclamation-circle' : 'fa-hourglass-half' }}"></i></div>
                <div class="pu-alert__body">
                    <div class="pu-alert__title">
                        {{ $supportExpired ? __('Envato support has expired') : __('Envato support is ending soon') }}
                    </div>
                    <div class="pu-alert__text">
                        {{ $supportExpired
                            ? __('You can still receive Digikash project updates for free. Extend Envato support for priority help.')
                            : __('Your support period is close to ending. Updates remain lifetime free, but extending support keeps premium help available.') }}
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Main two-column grid: License + Updates ────────────────── --}}
        <div class="pu-grid" data-show="idle-nolicense license-active update-available install-success install-failed">

            {{-- License panel: no license ──────────────────────────────── --}}
            <section class="pu-card" data-show="idle-nolicense">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon pu-card__head-icon--primary"><i class="fas fa-key"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('License') }}</h2>
                            <p class="pu-card__subtitle">{{ __('Activate your Envato purchase code to enable updates.') }}</p>
                        </div>
                    </div>
                    <span class="pu-pill pu-pill--plain"><span class="pu-pill__dot"></span>{{ __('Not linked') }}</span>
                </header>
                <div class="pu-card__body">
                    @can('project-updater-manage')
                        <form id="pu-activate-form" action="{{ route('admin.app.updater.activate') }}" method="POST" class="pu-form">
                            @csrf
                            <label class="pu-form__label" for="pu-license-input">{{ __('Envato Purchase Code') }}</label>
                            <input id="pu-license-input"
                                name="purchase_code"
                                type="text"
                                class="pu-input"
                                value="{{ old('purchase_code') }}"
                                placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
                                maxlength="40"
                                autocomplete="off">
                            @error('purchase_code')
                                <div class="pu-form__hint pu-form__hint--error">{{ $message }}</div>
                            @else
                                <div id="pu-license-hint" class="pu-form__hint">
                                    {{ __('Find your purchase code in your Envato downloads.') }}
                                </div>
                            @enderror
                            <div style="display:flex; align-items:center; gap:10px; margin-top:6px;">
                                <button type="submit" id="pu-license-activate" class="pu-btn pu-btn--primary" disabled>
                                    <i class="fas fa-key"></i> {{ __('Activate') }}
                                </button>
                                <span style="font-size:12px; color:var(--pu-text-muted); margin-left:auto;">
                                    {{ __('Envato support is for premium service & priority help only — updates are lifetime free.') }}
                                </span>
                            </div>
                        </form>
                    @endcan
                </div>
            </section>

            {{-- License panel: active ──────────────────────────────────── --}}
            <section class="pu-card" data-show="license-active update-available install-success install-failed">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon pu-card__head-icon--{{ $licenseActive ? 'success' : 'warning' }}"><i class="fas fa-id-card"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('License') }}</h2>
                            <p class="pu-card__subtitle">{{ __('Bound to this installation.') }}</p>
                        </div>
                    </div>
                    <span class="pu-pill pu-pill--{{ $licenseActive ? 'success' : 'warning' }}">
                        <span class="pu-pill__dot"></span>{{ $licenseActive ? __('Active') : __('Inactive') }}
                    </span>
                </header>
                <div class="pu-card__body">
                    @if($license)
                        <div class="pu-facts">
                            <div class="pu-fact">
                                <span class="pu-fact__k"><i class="fas fa-user"></i> {{ __('Buyer') }}</span>
                                <span class="pu-fact__v pu-mono">{{ $license->buyer_username ?: __('Unknown') }}</span>
                            </div>
                            <div class="pu-fact">
                                <span class="pu-fact__k"><i class="fas fa-globe"></i> {{ __('Domain') }}</span>
                                <span class="pu-fact__v pu-mono">{{ $license->domain ?: request()->getHost() }}</span>
                            </div>
                            <div class="pu-fact">
                                <span class="pu-fact__k"><i class="fas fa-calendar"></i> {{ __('Activated') }}</span>
                                <span class="pu-fact__v">{{ $license->activated_at?->format('M d, Y') ?? __('Unknown') }}</span>
                            </div>
                            <div class="pu-fact">
                                <span class="pu-fact__k"><i class="fas fa-life-ring"></i> {{ __('Support Until') }}</span>
                                <span class="pu-fact__v">{{ $license->support_until?->format('M d, Y') ?? __('Unknown') }}</span>
                            </div>
                            <div class="pu-fact">
                                <span class="pu-fact__k"><i class="fas fa-clock"></i> {{ __('Last Checked') }}</span>
                                <span class="pu-fact__v">{{ $license->last_checked_at?->diffForHumans() ?? __('Never') }}</span>
                            </div>
                            <div class="pu-fact">
                                <span class="pu-fact__k"><i class="fas fa-infinity"></i> {{ __('Update Access') }}</span>
                                <span class="pu-fact__v">{{ __('Lifetime Free') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
                @can('project-updater-manage')
                    @if($license)
                        @php
                            $hasUsableCode = $license->hasUsablePurchaseCode();
                        @endphp
                        <footer class="pu-card__foot" style="flex-wrap:wrap; gap:10px;">
                            <span style="font-size:var(--pu-fs-xs); color:var(--pu-text-muted); flex:1; min-width:240px;">
                                @if($hasUsableCode)
                                    {{ __('Seeing "Unknown license token" errors? Refresh to fetch a new token from your stored purchase code.') }}
                                @else
                                    {{ __('Stored purchase code cannot be decrypted (APP_KEY may have changed). Re-enter your Envato purchase code to re-bind this installation.') }}
                                @endif
                            </span>
                            <div style="display:flex; gap:8px; align-items:center;">
                                @if($hasUsableCode)
                                    <form id="pu-refresh-license-form" action="{{ route('admin.app.updater.refresh-license') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="pu-btn pu-btn--ghost pu-btn--sm">
                                            <i class="fas fa-arrows-rotate"></i> {{ __('Refresh license') }}
                                        </button>
                                    </form>
                                @endif
                                <button type="button" class="pu-btn pu-btn--{{ $hasUsableCode ? 'ghost' : 'primary' }} pu-btn--sm"
                                    data-action="toggle-reactivate">
                                    <i class="fas fa-key"></i> {{ __('Re-activate') }}
                                </button>
                            </div>
                        </footer>
                        <div class="pu-reactivate" id="pu-reactivate-panel" hidden style="padding:0 20px 18px;">
                            <form action="{{ route('admin.app.updater.activate') }}" method="POST" class="pu-form" id="pu-reactivate-form">
                                @csrf
                                <label class="pu-form__label" for="pu-reactivate-input">{{ __('Envato Purchase Code') }}</label>
                                <input id="pu-reactivate-input"
                                    name="purchase_code"
                                    type="text"
                                    class="pu-input"
                                    placeholder="XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX"
                                    maxlength="40"
                                    autocomplete="off">
                                <div id="pu-reactivate-hint" class="pu-form__hint">
                                    {{ __('Re-entering your purchase code updates this installation\'s license token.') }}
                                </div>
                                <div style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                    <button type="submit" id="pu-reactivate-submit" class="pu-btn pu-btn--primary pu-btn--sm" disabled>
                                        <i class="fas fa-key"></i> {{ __('Re-activate license') }}
                                    </button>
                                    <button type="button" class="pu-btn pu-btn--ghost pu-btn--sm" data-action="toggle-reactivate">
                                        {{ __('Cancel') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcan
            </section>

            {{-- Updates panel: idle-nolicense (locked) ─────────────────── --}}
            <section class="pu-card" data-show="idle-nolicense">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon"><i class="fas fa-lock"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('Updates') }}</h2>
                            <p class="pu-card__subtitle">{{ __('Activate a license to receive new releases.') }}</p>
                        </div>
                    </div>
                    <span class="pu-pill pu-pill--plain"><span class="pu-pill__dot"></span>{{ __('Locked') }}</span>
                </header>
                <div class="pu-card__body">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:44px; height:44px; border-radius:10px; background:var(--pu-surface-soft); display:grid; place-items:center; color:var(--pu-text-muted); font-size:18px;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div style="font-weight:600;">{{ __('Update checks disabled') }}</div>
                            <div style="color:var(--pu-text-soft); font-size:var(--pu-fs-sm);">
                                {{ __('Digikash needs a verified purchase code to fetch updates.') }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Updates panel: up-to-date ───────────────────────────────── --}}
            <section class="pu-card" data-show="license-active install-success">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon pu-card__head-icon--success"><i class="fas fa-circle-check"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('Release') }}</h2>
                            <p class="pu-card__subtitle">{{ __("You're running the latest stable release.") }}</p>
                        </div>
                    </div>
                    <span class="pu-pill pu-pill--success"><span class="pu-pill__dot"></span>{{ __('Up to date') }}</span>
                </header>
                <div class="pu-card__body">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:44px; height:44px; border-radius:10px; background:var(--pu-success-soft); display:grid; place-items:center; color:var(--pu-success-deep); font-size:18px;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div style="font-weight:600;">{{ __("You're on the latest version") }}</div>
                            <div style="color:var(--pu-text-soft); font-size:var(--pu-fs-sm);">
                                {{ __('Last checked') }} {{ $latest?->checked_at?->diffForHumans() ?? __('never') }}
                                @if($latest?->channel) · {{ __('channel') }} <span class="pu-mono">{{ $latest->channel }}</span> @endif
                            </div>
                        </div>
                    </div>
                    <div class="pu-facts">
                        <div class="pu-fact"><span class="pu-fact__k">{{ __('Current version') }}</span><span class="pu-fact__v pu-mono">v{{ config('app.version') }}</span></div>
                        <div class="pu-fact"><span class="pu-fact__k">{{ __('Channel') }}</span><span class="pu-fact__v">{{ $latest?->channel ?? config('project_updater.channel') }}</span></div>
                        <div class="pu-fact"><span class="pu-fact__k">{{ __('Released') }}</span><span class="pu-fact__v">{{ $latest?->release_date?->format('M d, Y') ?? __('Unknown') }}</span></div>
                    </div>
                </div>
            </section>

            {{-- Updates panel: update available ─────────────────────────── --}}
            <section class="pu-card" data-show="update-available">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon pu-card__head-icon--warning"><i class="fas fa-rocket"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('Release') }}</h2>
                            <p class="pu-card__subtitle">
                                {{ __('Version :v is available', ['v' => $latest?->version]) }}
                                @if($latest?->release_date)
                                    · {{ __('released :date', ['date' => $latest->release_date->format('M d, Y')]) }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <span class="pu-pill pu-pill--warning"><span class="pu-pill__dot"></span>{{ __('Update ready') }}</span>
                </header>
                <div class="pu-card__body">
                    <div style="display:flex; align-items:center; gap:12px; padding:14px; background:var(--pu-primary-soft); border-radius:var(--pu-radius-md); border:1px solid color-mix(in srgb, var(--pu-primary) 18%, var(--pu-border));">
                        <div style="width:44px; height:44px; border-radius:10px; background:var(--pu-card); display:grid; place-items:center; color:var(--pu-primary-deep); font-size:18px;">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div style="font-weight:600;">
                                {{ __('Digikash') }}
                                <span class="pu-mono">v{{ $latest?->version }}</span>
                                {{ __('is ready to install') }}
                            </div>
                            <div style="color:var(--pu-text-soft); font-size:var(--pu-fs-sm);">
                                {{ __('Channel') }} <span class="pu-mono">{{ $latest?->channel ?? config('project_updater.channel') }}</span>
                            </div>
                        </div>
                        @can('project-updater-manage')
                            <button type="button" class="pu-btn pu-btn--primary" data-action="open-install-modal">
                                <i class="fas fa-download"></i> {{ __('Install') }}
                            </button>
                        @endcan
                    </div>

                    @if($changelog->isNotEmpty())
                        <div>
                            <div class="pu-section-head">
                                <span class="pu-section-head__title">{{ __("What's new") }}</span>
                            </div>
                            <ul class="pu-changelog">
                                @foreach($changelog->take(8) as $line)
                                    @php
                                        $tag = strtolower($line['tag'] ?? 'changed');
                                        $tagClass = in_array($tag, ['added', 'fixed', 'changed', 'security']) ? $tag : 'changed';
                                    @endphp
                                    <li class="pu-changelog__item">
                                        <span class="pu-changelog__tag pu-changelog__tag--{{ $tagClass }}">{{ $tag }}</span>
                                        <span class="pu-changelog__text">{{ $line['text'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($latest?->checksum)
                        <div style="display:flex; gap:14px; padding-top:8px; border-top:1px solid var(--pu-border-soft); font-size:var(--pu-fs-xs); color:var(--pu-text-muted); font-family:var(--pu-font-mono);">
                            <span>SHA-256
                                <span class="pu-copy">
                                    <span class="pu-copy__val">{{ $latest->checksum }}</span>
                                    <button type="button" class="pu-copy__btn" aria-label="{{ __('Copy') }}"><i class="fas fa-copy"></i></button>
                                </span>
                            </span>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Updates panel: install-failed recovery info ────────────── --}}
            <section class="pu-card" data-show="install-failed">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon pu-card__head-icon--danger"><i class="fas fa-circle-exclamation"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('Updates') }}</h2>
                            <p class="pu-card__subtitle">{{ __('Previous install attempt failed and was rolled back.') }}</p>
                        </div>
                    </div>
                    <span class="pu-pill pu-pill--danger"><span class="pu-pill__dot"></span>{{ __('Install failed') }}</span>
                </header>
                <div class="pu-card__body">
                    <div style="display:flex; align-items:flex-start; gap:12px; padding:14px; background:var(--pu-danger-soft); border-radius:var(--pu-radius-md); border:1px solid color-mix(in srgb, var(--pu-danger) 20%, var(--pu-border));">
                        <div style="width:32px; height:32px; border-radius:8px; background:var(--pu-card); display:grid; place-items:center; color:var(--pu-danger-deep); font-size:14px; flex-shrink:0;">
                            <i class="fas fa-circle-exclamation"></i>
                        </div>
                        <div style="min-width:0; flex:1;">
                            <div style="font-weight:600; color:var(--pu-danger-deep); margin-bottom:4px;">{{ __('What happened') }}</div>
                            <div style="font-size:var(--pu-fs-sm); line-height:1.5;">
                                {{ $latest?->error_message ?: __('Install failed unexpectedly. Check the project updater log for details.') }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="pu-section-head"><span class="pu-section-head__title">{{ __('Recovery info') }}</span></div>
                        <div style="display:flex; flex-direction:column; gap:8px; font-size:var(--pu-fs-sm); color:var(--pu-text-soft);">
                            @if($latest?->backup_path)
                                <div style="display:flex; gap:10px; align-items:flex-start;">
                                    <i class="fas fa-check" style="color:var(--pu-success); margin-top:3px; font-size:11px;"></i>
                                    <span>{{ __('Backup preserved at') }} <code style="font-size:11px;">{{ $latest->backup_path }}</code></span>
                                </div>
                            @endif
                            <div style="display:flex; gap:10px; align-items:flex-start;">
                                <i class="fas fa-check" style="color:var(--pu-success); margin-top:3px; font-size:11px;"></i>
                                <span>{{ __('Maintenance mode lifted') }}</span>
                            </div>
                            <div style="display:flex; gap:10px; align-items:flex-start;">
                                <i class="fas fa-info-circle" style="color:var(--pu-info); margin-top:3px; font-size:11px;"></i>
                                <span>{{ __('Check storage/logs for the full error trace') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @can('project-updater-manage')
                    <footer class="pu-card__foot">
                        <span style="font-size:var(--pu-fs-xs); color:var(--pu-text-muted);">
                            {{ __('Most transient failures resolve on retry.') }}
                        </span>
                        <button type="button" class="pu-btn pu-btn--primary pu-btn--sm" data-action="open-install-modal">
                            <i class="fas fa-rotate-right"></i> {{ __('Retry install') }}
                        </button>
                    </footer>
                @endcan
            </section>
        </div>

        {{-- ── Environment readiness ───────────────────────────────────── --}}
        <section class="pu-card" data-show="idle-nolicense license-active update-available install-success install-failed" style="margin-bottom:24px;">
            <header class="pu-card__head">
                <div class="pu-card__head-main">
                    <span class="pu-card__head-icon pu-card__head-icon--{{ $checksReady ? 'success' : 'warning' }}"><i class="fas fa-heart-pulse"></i></span>
                    <div class="pu-card__head-text">
                        <h2 class="pu-card__title">{{ __('Environment readiness') }}</h2>
                        <p class="pu-card__subtitle">{{ __('Pre-flight checks — all must pass before installing.') }}</p>
                    </div>
                </div>
                <span class="pu-pill pu-pill--{{ $checksReady ? 'success' : 'warning' }}">
                    <span class="pu-pill__dot"></span>{{ $checksPassed }} {{ __('of') }} {{ $checksTotal }} {{ __('passing') }}
                </span>
            </header>
            <div class="pu-checks">
                @foreach($checks as $check)
                    <div class="pu-check-row">
                        <div class="pu-check-row__icon pu-check-row__icon--{{ $check['status'] ? 'ok' : 'fail' }}">
                            <i class="fas {{ $check['status'] ? 'fa-check' : 'fa-xmark' }}"></i>
                        </div>
                        <div class="pu-check-row__label" title="{{ $check['help'] ?? '' }}">{{ $check['label'] }}</div>
                        <div class="pu-check-row__value">{{ $check['value'] ?? ($check['status'] ? __('ok') : __('check')) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ── Recovery backup card ───────────────────────────────────── --}}
        @can('project-updater-manage')
            <section class="pu-card" data-show="idle-nolicense license-active update-available install-success install-failed" style="margin-bottom:24px;">
                <header class="pu-card__head">
                    <div class="pu-card__head-main">
                        <span class="pu-card__head-icon pu-card__head-icon--info"><i class="fas fa-life-ring"></i></span>
                        <div class="pu-card__head-text">
                            <h2 class="pu-card__title">{{ __('Recovery backup') }}</h2>
                            <p class="pu-card__subtitle"
                                title="{{ __('Generate a ZIP of your current database + storage folder. Important: download a local backup before updating.') }}">
                                {{ __('Snapshot the database + storage folder before any update.') }}
                            </p>
                        </div>
                    </div>
                    <form action="{{ route('admin.app.updater.backup.download') }}" method="POST">
                        @csrf
                        <button type="submit" class="pu-btn pu-btn--secondary" @disabled(! $licenseActive)>
                            <i class="fas fa-download"></i> {{ __('Generate recovery backup') }}
                        </button>
                    </form>
                </header>
            </section>
        @endcan

        {{-- ── Install history (collapsible) ──────────────────────────── --}}
        <section class="pu-card pu-collapse" id="pu-history-collapse" data-open="false"
            data-show="idle-nolicense license-active update-available install-success install-failed">
            <header class="pu-card__head">
                <div class="pu-card__head-main">
                    <span class="pu-card__head-icon"><i class="fas fa-clock-rotate-left"></i></span>
                    <div class="pu-card__head-text">
                        <h2 class="pu-card__title">{{ __('Update History') }}</h2>
                        <p class="pu-card__subtitle">{{ __('Recent update activity on this installation.') }}</p>
                    </div>
                </div>
                @if($history->isNotEmpty())
                    <button type="button" class="pu-collapse__toggle" data-action="toggle-history">
                        <i class="fas fa-chevron-right"></i>
                        <span class="pu-collapse__toggle-label">{{ __('Show install history') }}</span>
                        <span style="color:var(--pu-text-faint); font-family:var(--pu-font-mono); font-size:11px;">
                            {{ trans_choice(':count entry|:count entries', $history->count(), ['count' => $history->count()]) }}
                        </span>
                    </button>
                @endif
            </header>
            <div class="pu-collapse__body">
                @if($history->isNotEmpty())
                    <div class="pu-table-wrap">
                        <table class="pu-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Version') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Checked') }}</th>
                                    <th>{{ __('Installed') }}</th>
                                    <th>{{ __('Backup') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($history as $update)
                                    <tr>
                                        <td><span class="pu-table__version">v{{ $update->version }}</span></td>
                                        <td><span class="pu-pill pu-pill--{{ $update->statusTone() }}"><span class="pu-pill__dot"></span>{{ title(str_replace('_', ' ', $update->status)) }}</span></td>
                                        <td><span class="pu-table__time">{{ $update->checked_at?->diffForHumans() ?? '—' }}</span></td>
                                        <td><span class="pu-table__time">{{ $update->installed_at?->diffForHumans() ?? '—' }}</span></td>
                                        <td><span class="pu-table__path">{{ $update->backup_path ?: '—' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="padding:18px 20px;">
                        <x-admin-not-found
                            :title="__('No update activity')"
                            :message="__('Activate the license and run your first update check.')"
                            icon="fa-history"
                        />
                    </div>
                @endif
            </div>
        </section>

        {{-- ══════════════════════════════════════════════════════════════
             INSTALL IN PROGRESS — pipeline + log
             ══════════════════════════════════════════════════════════════ --}}
        <div data-show="install-progress">

            <header style="display:flex; align-items:center; justify-content:space-between; gap:18px; padding:28px 0 22px; border-bottom:1px solid var(--pu-border); margin-bottom:24px;">
                <div>
                    <div class="pu-hero__eyebrow">{{ __('App management · install run') }}</div>
                    <h1 style="font-size:var(--pu-fs-3xl); font-weight:600; margin:6px 0 8px; letter-spacing:-0.015em;">
                        {{ __('Installing :v', ['v' => $latest?->version]) }}
                    </h1>
                    <div class="pu-hero__status pu-hero__status--warning">
                        <span class="pu-hero__status-dot"></span>
                        <span>{{ __('Install in progress · do not close this window') }}</span>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="pu-pill pu-pill--info" style="font-family:var(--pu-font-mono);">
                        <i class="fas fa-id-card" style="font-size:9px;"></i> {{ __('Maintenance mode active') }}
                    </span>
                    <span class="pu-version">
                        <span class="pu-version__label">{{ __('From') }}</span>
                        <span class="pu-version__value">v{{ config('app.version') }}</span>
                        <span style="color:var(--pu-text-faint);">→</span>
                        <span class="pu-version__value">v{{ $latest?->version }}</span>
                    </span>
                </div>
            </header>

            <div class="pu-install-head">
                <div class="pu-install-head__main">
                    <div class="pu-install-head__title">
                        <i class="fas fa-rotate fa-spin" style="font-size:13px; color:var(--pu-primary);"></i>
                        {{ __('Overall progress') }}
                    </div>
                    <div class="pu-install-head__meta">
                        <span>{{ __('Elapsed') }} <strong id="pu-elapsed">0s</strong></span>
                        <span id="pu-remaining">{{ __('estimating…') }}</span>
                        <span>{{ __('Progress') }} <strong id="pu-overall-pct">0%</strong></span>
                    </div>
                    <div class="pu-progress" style="margin-top:14px;">
                        <div class="pu-progress__bar" id="pu-overall-bar"></div>
                    </div>
                </div>
            </div>

            <div class="pu-install-grid">
                <section class="pu-card">
                    <header class="pu-card__head">
                        <div class="pu-card__head-main">
                            <span class="pu-card__head-icon pu-card__head-icon--primary"><i class="fas fa-list-check"></i></span>
                            <div class="pu-card__head-text">
                                <h2 class="pu-card__title">{{ __('Pipeline') }}</h2>
                                <p class="pu-card__subtitle">{{ __('Steps the updater runs in order.') }}</p>
                            </div>
                        </div>
                    </header>
                    <div class="pu-card__body" style="gap:0; padding:12px 14px;">
                        <div class="pu-pipeline" id="pu-pipeline"></div>
                    </div>
                </section>

                <section class="pu-card">
                    <header class="pu-card__head">
                        <div class="pu-card__head-main">
                            <span class="pu-card__head-icon pu-card__head-icon--primary"><i class="fas fa-gears"></i></span>
                            <div class="pu-card__head-text">
                                <div class="pu-detail__phase" id="pu-detail-phase">{{ __('Phase 1 of 11') }}</div>
                                <h2 class="pu-detail__title" id="pu-detail-title">{{ __('Preparing…') }}</h2>
                            </div>
                        </div>
                    </header>
                    <div class="pu-card__body" style="gap:18px;">
                        <div class="pu-detail__action" id="pu-detail-action"><strong>{{ __('Initialising update.') }}</strong></div>

                        <div class="pu-stats">
                            <div class="pu-stat">
                                <div class="pu-stat__label" id="pu-stat-1-label">{{ __('Phase elapsed') }}</div>
                                <div class="pu-stat__value" id="pu-stat-1-value">0.0s</div>
                                <div class="pu-stat__sub" id="pu-stat-1-sub"></div>
                            </div>
                            <div class="pu-stat">
                                <div class="pu-stat__label" id="pu-stat-2-label">{{ __('Progress') }}</div>
                                <div class="pu-stat__value" id="pu-stat-2-value">—</div>
                                <div class="pu-stat__sub" id="pu-stat-2-sub"></div>
                            </div>
                            <div class="pu-stat">
                                <div class="pu-stat__label" id="pu-stat-3-label">{{ __('Step') }}</div>
                                <div class="pu-stat__value" id="pu-stat-3-value">1 / 11</div>
                                <div class="pu-stat__sub" id="pu-stat-3-sub"></div>
                            </div>
                        </div>

                        <div class="pu-collapse" id="pu-log-collapse" data-open="true">
                            <button type="button" class="pu-collapse__toggle" data-action="toggle-log">
                                <i class="fas fa-chevron-right"></i>
                                <span class="pu-collapse__toggle-label">{{ __('Hide technical log') }}</span>
                                <span style="color:var(--pu-text-faint); font-family:var(--pu-font-mono); font-size:11px;">streaming</span>
                            </button>
                            <div class="pu-collapse__body" style="margin-top:6px;">
                                <div class="pu-log">
                                    <div class="pu-log__head">
                                        <span><i class="fas fa-terminal" style="font-size:10px; margin-right:6px;"></i> project-updater.log</span>
                                        <div class="pu-log__head-actions">
                                            <button type="button" class="pu-log__head-btn is-active" data-action="toggle-autoscroll">
                                                <i class="fas fa-arrow-down"></i><span class="label">{{ __('Auto-scroll on') }}</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="pu-log__body" id="pu-log-body"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    {{-- ── Install confirmation modal ──────────────────────────────────── --}}
    @can('project-updater-manage')
        @if($latest && in_array($latest->status, ['available', 'failed'], true))
            <div class="pu-overlay" id="pu-install-modal" data-open="false">
                <div class="pu-modal project-updater" data-state="modal" role="dialog" aria-labelledby="pu-install-modal-title">
                    <header class="pu-modal__head">
                        <h2 class="pu-modal__title" id="pu-install-modal-title">
                            {{ __('Install version :v?', ['v' => $latest->version]) }}
                        </h2>
                        <p class="pu-modal__sub">
                            {{ __('Confirm settings — the install begins immediately and your site will be unreachable for ~90 seconds.') }}
                        </p>
                    </header>
                    <div class="pu-modal__body">
                        <div class="pu-summary-row">
                            <div>
                                <div class="pu-summary-row__label">{{ __('Version') }}</div>
                                <div class="pu-summary-row__value">v{{ config('app.version') }} → v{{ $latest->version }}</div>
                            </div>
                            <div>
                                <div class="pu-summary-row__label">{{ __('Channel') }}</div>
                                <div class="pu-summary-row__value">{{ $latest->channel ?? config('project_updater.channel') }}</div>
                            </div>
                            <div>
                                <div class="pu-summary-row__label">{{ __('Est. duration') }}</div>
                                <div class="pu-summary-row__value">~90s</div>
                            </div>
                        </div>

                        <form id="pu-install-form" action="{{ route('admin.app.updater.install', $latest) }}" method="POST">
                            @csrf
                            <input type="hidden" name="backup_database_storage" id="pu-install-backup-hidden" value="1">

                            <label class="pu-check" style="margin-bottom:12px;">
                                <input type="checkbox" id="pu-install-backup" checked>
                                <div>
                                    <div class="pu-check__main">{{ __('Backup database and storage folder') }}</div>
                                    <div class="pu-check__sub">
                                        {{ __('Recommended. Saved to') }}
                                        <span class="pu-mono">storage/app/{{ config('project_updater.backups_path') }}/</span>
                                    </div>
                                </div>
                            </label>

                            <label class="pu-check" aria-disabled="true">
                                <input type="checkbox" checked disabled>
                                <div>
                                    <div class="pu-check__main">{{ __('Enable maintenance mode during install') }}</div>
                                    <div class="pu-check__sub">{{ __('Required. Customers see a maintenance page until install completes.') }}</div>
                                </div>
                            </label>
                        </form>

                        <div class="pu-alert pu-alert--warning" style="margin:0; padding:12px 14px;">
                            <div class="pu-alert__icon"><i class="fas fa-triangle-exclamation"></i></div>
                            <div class="pu-alert__body">
                                <div class="pu-alert__title" style="font-size:var(--pu-fs-sm);">
                                    {{ __('Important: download a local backup before updating') }}
                                </div>
                                <div class="pu-alert__text">
                                    {{ __('If the update fails and no local backup was kept, restore may not be possible and support may be limited. Click Generate recovery backup before installing.') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="pu-modal__foot">
                        <button type="button" class="pu-btn pu-btn--ghost" data-action="close-install-modal">
                            {{ __('Cancel') }}
                        </button>
                        <button type="button" class="pu-btn pu-btn--primary" id="pu-install-confirm">
                            <i class="fas fa-rocket"></i> {{ __('Install Update') }}
                        </button>
                    </footer>
                </div>
            </div>
        @endif
    @endcan

    {{-- Server troubleshooting modal — always rendered, opened via JS. --}}
    @include('backend.app.partials._updater_troubleshooting')
@endsection

@push('scripts')
    <script>
        window.projectUpdaterConfig = {
            currentVersion: @json(config('app.version')),
            latestVersion: @json($latest?->version),
            channel: @json($latest?->channel ?? config('project_updater.channel')),
            i18n: {
                installing: @json(__('Installing…')),
                verifying: @json(__('Verifying…')),
                checking: @json(__('Checking…')),
                preparing: @json(__('Preparing…')),
                refreshing: @json(__('Refreshing…')),
                generateBackup: @json(__('Generate recovery backup')),
                showLog: @json(__('Show technical log')),
                hideLog: @json(__('Hide technical log')),
                showHistory: @json(__('Show install history')),
                hideHistory: @json(__('Hide install history')),
                autoScrollOn: @json(__('Auto-scroll on')),
                autoScrollOff: @json(__('Auto-scroll off')),
                licenseHintChars: @json(__('characters')),
                copied: @json(__('Copied'))
            }
        };
    </script>
    @php
        $puJsVersion = config('app.version').'-'.filemtime(public_path('backend/js/project-updater.js'));
    @endphp
    <script src="{{ asset('backend/js/project-updater.js?v='.$puJsVersion) }}"></script>
@endpush
