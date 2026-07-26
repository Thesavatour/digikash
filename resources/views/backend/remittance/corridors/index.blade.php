@extends('backend.layouts.app')
@section('title', __('Remittance Corridors'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('backend/css/remittance.css?v=' . filemtime(public_path('backend/css/remittance.css'))) }}">
@endpush

@section('content')
    @php
        /*
         * Status registry for corridors. Active / Disabled only,
         * but kept in the same shape as the gift-card admin status
         * map so the .rmt-admin-pill component stays consistent.
         */
        $statusFilterOptions = [
            'active'   => ['label' => __('Active'),   'cls' => 'success'],
            'disabled' => ['label' => __('Disabled'), 'cls' => 'secondary'],
        ];

        $siteCurrencySymbol = siteCurrency('symbol') ?? '$';
    @endphp

    <div class="remittance-admin">

        {{-- Hero header — mirrors the edit corridor hero so the
             remittance section keeps one consistent visual identity:
             icon tile + eyebrow + title on the left, primary action
             + decorative world map on the right. --}}
        <section class="rmt-hero rmt-hero--index" aria-labelledby="rmt-hero-title">
            <div class="rmt-hero__grid">
                {{-- LEFT: identity + meta --}}
                <div class="rmt-hero__main">
                    <div class="rmt-hero__head">
                        <div class="rmt-pagehead__icon rmt-pagehead__icon--gradient" aria-hidden="true">
                            <i class="fa-solid fa-globe"></i>
                        </div>

                        <div class="rmt-hero__head-body">
                            <div class="rmt-pagehead__topline">
                                <span class="rmt-pagehead__eyebrow">
                                    <i class="fa-solid fa-route" aria-hidden="true"></i>
                                    {{ __('Remittance') }}
                                </span>
                                @if ($stats['active'] > 0)
                                    <span class="rmt-pagehead__pill rmt-pagehead__pill--success">
                                        <span class="rmt-pagehead__pill-dot"></span>
                                        {{ number_format($stats['active']) }} {{ __('Active') }}
                                    </span>
                                @endif
                                @if ($stats['total'] - $stats['active'] > 0)
                                    <span class="rmt-pagehead__pill rmt-pagehead__pill--muted">
                                        <span class="rmt-pagehead__pill-dot"></span>
                                        {{ number_format($stats['total'] - $stats['active']) }} {{ __('Disabled') }}
                                    </span>
                                @endif
                            </div>

                            <h1 id="rmt-hero-title" class="rmt-hero__title">{{ __('Corridors') }}</h1>
                            <p class="rmt-hero__subtitle">{{ __('Manage country pairs, FX margin, fees, and payout gateways for cross-border transfers.') }}</p>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: primary action (top) + decorative world map (below) --}}
                <div class="rmt-hero__side">
                    @can('remittance-corridor-manage')
                        <a href="{{ route('admin.remittance.corridors.create') }}" class="btn btn-primary rmt-hero__action">
                            <x-icon name="add" width="16" height="16"/>
                            <span>{{ __('Add Corridor') }}</span>
                        </a>
                    @endcan

                    <aside class="rmt-hero__globe" aria-hidden="true">
                        <svg class="rmt-hero__globe-svg" viewBox="0 0 1000 500" preserveAspectRatio="xMidYMid meet" focusable="false">
                            <defs>
                                <pattern id="rmt-map-dots-index" x="0" y="0" width="10" height="10" patternUnits="userSpaceOnUse">
                                    <circle cx="2" cy="2" r="1.6" fill="#94a3b8"/>
                                </pattern>
                                <mask id="rmt-map-mask-index">
                                    {{-- North America --}}
                                    <path d="M 60 70 L 130 60 L 200 60 L 280 70 L 330 85 L 355 110 L 360 145 L 355 175 L 345 200 L 325 220 L 295 235 L 260 245 L 225 240 L 195 225 L 170 205 L 150 180 L 130 160 L 110 140 L 90 115 L 75 95 Z" fill="#ffffff"/>
                                    {{-- Greenland --}}
                                    <path d="M 380 55 L 425 50 L 445 75 L 440 105 L 415 115 L 390 105 L 378 80 Z" fill="#ffffff"/>
                                    {{-- South America --}}
                                    <path d="M 280 265 L 325 270 L 350 290 L 365 325 L 365 380 L 350 425 L 325 460 L 300 470 L 280 460 L 270 425 L 263 380 L 263 325 L 270 290 Z" fill="#ffffff"/>
                                    {{-- Europe --}}
                                    <path d="M 470 90 L 515 80 L 555 90 L 570 115 L 560 140 L 525 148 L 490 145 L 470 130 L 460 110 Z" fill="#ffffff"/>
                                    {{-- UK/Ireland --}}
                                    <path d="M 460 110 L 475 105 L 478 125 L 466 130 Z" fill="#ffffff"/>
                                    {{-- Africa --}}
                                    <path d="M 495 180 L 555 180 L 585 200 L 595 245 L 585 295 L 565 340 L 540 370 L 515 380 L 495 365 L 478 330 L 470 280 L 475 230 L 485 200 Z" fill="#ffffff"/>
                                    {{-- Madagascar --}}
                                    <path d="M 600 340 L 615 340 L 620 365 L 612 385 L 600 380 Z" fill="#ffffff"/>
                                    {{-- Asia (main) --}}
                                    <path d="M 555 85 L 620 70 L 700 65 L 780 75 L 840 95 L 880 120 L 905 150 L 920 185 L 920 220 L 895 245 L 855 260 L 815 265 L 775 270 L 740 260 L 705 250 L 675 240 L 645 230 L 615 215 L 590 195 L 575 165 L 565 130 Z" fill="#ffffff"/>
                                    {{-- Indian Subcontinent --}}
                                    <path d="M 695 245 L 745 250 L 760 290 L 745 320 L 720 320 L 700 295 L 690 270 Z" fill="#ffffff"/>
                                    {{-- SE Asia / Indonesia --}}
                                    <path d="M 790 275 L 845 280 L 895 305 L 870 325 L 815 325 L 790 305 Z" fill="#ffffff"/>
                                    {{-- Japan --}}
                                    <path d="M 875 145 L 895 138 L 910 170 L 895 195 L 880 180 Z" fill="#ffffff"/>
                                    {{-- Philippines --}}
                                    <path d="M 855 245 L 870 245 L 873 275 L 860 285 L 853 265 Z" fill="#ffffff"/>
                                    {{-- Australia --}}
                                    <path d="M 825 350 L 905 345 L 935 365 L 920 395 L 870 405 L 825 395 L 815 375 Z" fill="#ffffff"/>
                                    {{-- New Zealand --}}
                                    <path d="M 945 410 L 960 410 L 965 435 L 950 440 L 943 425 Z" fill="#ffffff"/>
                                    {{-- Antarctica --}}
                                    <path d="M 80 465 L 920 465 L 920 488 L 80 488 Z" fill="#ffffff"/>
                                </mask>
                                <radialGradient id="rmt-pin-blue-index" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#4663EE" stop-opacity="0.35"/>
                                    <stop offset="100%" stop-color="#4663EE" stop-opacity="0"/>
                                </radialGradient>
                                <radialGradient id="rmt-pin-green-index" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#16a34a" stop-opacity="0.35"/>
                                    <stop offset="100%" stop-color="#16a34a" stop-opacity="0"/>
                                </radialGradient>
                                <radialGradient id="rmt-pin-purple-index" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#6D28D9" stop-opacity="0.35"/>
                                    <stop offset="100%" stop-color="#6D28D9" stop-opacity="0"/>
                                </radialGradient>
                                <radialGradient id="rmt-pin-amber-index" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.35"/>
                                    <stop offset="100%" stop-color="#F59E0B" stop-opacity="0"/>
                                </radialGradient>
                            </defs>

                            {{-- Soft background --}}
                            <rect x="0" y="0" width="1000" height="500" fill="#F8FAFC" rx="8"/>

                            {{-- Dotted world map (continent silhouettes only) --}}
                            <rect x="0" y="0" width="1000" height="500" fill="url(#rmt-map-dots-index)" mask="url(#rmt-map-mask-index)" opacity="0.55"/>

                            {{-- Decorative pins on major corridor hubs --}}
                            <circle cx="294" cy="139" r="18" fill="url(#rmt-pin-blue-index)"/>
                            <circle cx="294" cy="139" r="5" fill="#4663EE"/>
                            <circle cx="294" cy="139" r="2" fill="#ffffff"/>

                            <circle cx="751" cy="184" r="18" fill="url(#rmt-pin-green-index)"/>
                            <circle cx="751" cy="184" r="5" fill="#16a34a"/>
                            <circle cx="751" cy="184" r="2" fill="#ffffff"/>

                            <circle cx="510" cy="120" r="18" fill="url(#rmt-pin-purple-index)"/>
                            <circle cx="510" cy="120" r="5" fill="#6D28D9"/>
                            <circle cx="510" cy="120" r="2" fill="#ffffff"/>

                            <circle cx="870" cy="370" r="18" fill="url(#rmt-pin-amber-index)"/>
                            <circle cx="870" cy="370" r="5" fill="#F59E0B"/>
                            <circle cx="870" cy="370" r="2" fill="#ffffff"/>
                        </svg>
                    </aside>
                </div>
            </div>
        </section>

        {{-- KPI strip --}}
        <div class="row g-3 mb-4 remittance-admin-kpis">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 remittance-admin-kpi" style="--kpi-bg:#DBEAFE; --kpi-fg:#1D4ED8;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="remittance-admin-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="6" cy="19" r="2"/>
                                <circle cx="18" cy="5" r="2"/>
                                <path d="M8 18 C 11 18, 12 14, 12 12 S 13 6, 16 6"/>
                            </svg>
                        </span>
                        <div>
                            <div class="remittance-admin-kpi__label">{{ __('Total Corridors') }}</div>
                            <div class="remittance-admin-kpi__value">{{ number_format($stats['total']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 remittance-admin-kpi" style="--kpi-bg:#DCFCE7; --kpi-fg:#15803D;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="remittance-admin-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 11 12 21 2 11"/>
                                <path d="M22 4L12 14 2 4"/>
                            </svg>
                        </span>
                        <div>
                            <div class="remittance-admin-kpi__label">{{ __('Active') }}</div>
                            <div class="remittance-admin-kpi__value">{{ number_format($stats['active']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 remittance-admin-kpi" style="--kpi-bg:#EDE9FE; --kpi-fg:#6D28D9;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="remittance-admin-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </span>
                        <div>
                            <div class="remittance-admin-kpi__label">{{ __('Transfers') }}</div>
                            <div class="remittance-admin-kpi__value">{{ number_format($stats['transfers']) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 h-100 remittance-admin-kpi" style="--kpi-bg:#FEF3C7; --kpi-fg:#92400E;">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="remittance-admin-kpi__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </span>
                        <div>
                            <div class="remittance-admin-kpi__label">{{ __('Completed Volume') }}</div>
                            <div class="remittance-admin-kpi__value">{{ $siteCurrencySymbol }}{{ number_format($stats['completed_value'], 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter + table --}}
        <div class="card border-0 mb-4 remittance-admin__card">
            <div class="card-body">
                <form method="GET" class="remittance-admin__filter mb-3">
                    <div class="remittance-admin__filter-search">
                        <i class="fa-solid fa-magnifying-glass remittance-admin__filter-search-icon" aria-hidden="true"></i>
                        <input type="text"
                               name="q"
                               value="{{ $search }}"
                               class="form-control remittance-admin__filter-input"
                               placeholder="{{ __('Search by name, country, currency, or gateway') }}">
                    </div>
                    <select name="status" class="form-select remittance-admin__filter-select">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statusFilterOptions as $key => $meta)
                            <option value="{{ $key }}" {{ $statusFilter === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary remittance-admin__filter-submit" type="submit">
                        <x-icon name="search" width="16" height="16"/>
                        <span>{{ __('Filter') }}</span>
                    </button>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle mb-0 rmt-admin-table">
                        <thead>
                            <tr>
                                <th>{{ __('Corridor') }}</th>
                                <th>{{ __('Gateway') }}</th>
                                <th>{{ __('FX Spread') }}</th>
                                <th>{{ __('Fee') }}</th>
                                <th>{{ __('Limits') }}</th>
                                <th>{{ __('Transfers') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($corridors as $corridor)
                                @php
                                    $statusMeta = $corridor->status
                                        ? ['label' => __('Active'),   'cls' => 'success']
                                        : ['label' => __('Disabled'), 'cls' => 'secondary'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="rmt-admin-corridor">
                                            <span class="rmt-admin-corridor__name">{{ $corridor->name }}</span>
                                            <span class="rmt-admin-corridor__pair" aria-label="{{ $corridor->source_country }} to {{ $corridor->destination_country }}">
                                                <span class="rmt-admin-corridor__code rmt-admin-corridor__code--from">{{ $corridor->source_country }}</span>
                                                <span class="rmt-admin-corridor__arrow" aria-hidden="true">
                                                    <span class="rmt-admin-corridor__arrow-line"></span>
                                                    <i class="fa-solid fa-caret-right"></i>
                                                </span>
                                                <span class="rmt-admin-corridor__code rmt-admin-corridor__code--to">{{ $corridor->destination_country }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="rmt-admin-gateway">
                                            <i class="fa-solid fa-plug rmt-admin-gateway__icon" aria-hidden="true"></i>
                                            {{ $corridor->payout_gateway }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="rmt-admin-stat">
                                            {{ number_format($corridor->fx_spread_percent, 2) }}<span class="rmt-admin-stat__unit">%</span>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="rmt-admin-fee">
                                            <span class="rmt-admin-fee__main">{{ number_format($corridor->fixed_fee, 2) }}</span>
                                            <span class="rmt-admin-fee__meta">+ {{ number_format($corridor->percent_fee, 2) }}%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rmt-admin-limits">
                                            <span class="rmt-admin-limits__range">{{ number_format($corridor->min_amount) }} – {{ number_format($corridor->max_amount) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="rmt-admin-count">{{ number_format($corridor->transfers_count) }}</span>
                                    </td>
                                    <td>
                                        <span class="rmt-admin-pill rmt-admin-pill--{{ $statusMeta['cls'] }}">
                                            <span class="rmt-admin-pill__dot" aria-hidden="true"></span>
                                            {{ $statusMeta['label'] }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="rmt-admin-actions">
                                            @can('remittance-corridor-manage')
                                                <a href="{{ route('admin.remittance.corridors.edit', $corridor) }}"
                                                   class="btn btn-outline-primary btn-sm rmt-admin-btn"
                                                   title="{{ __('Edit corridor') }}">
                                                    <x-icon name="edit" width="14" height="14"/>
                                                    <span>{{ __('Edit') }}</span>
                                                </a>

                                                <form action="{{ route('admin.remittance.corridors.destroy', $corridor) }}"
                                                      method="POST"
                                                      class="rmt-admin-actions__form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-outline-danger btn-sm rmt-admin-btn"
                                                            onclick="return confirm('{{ __('Delete this corridor?') }}')"
                                                            title="{{ __('Delete corridor') }}">
                                                        <x-icon name="trash" width="14" height="14"/>
                                                        <span>{{ __('Delete') }}</span>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-admin-not-found
                                            :title="$search || $statusFilter ? __('No corridors match your filter') : __('No corridors yet')"
                                            :message="$search || $statusFilter ? __('Try a different search term or clear the filter.') : __('Add your first corridor to start accepting international transfers.')"
                                            icon="fa-globe"
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($corridors->hasPages())
                    <div class="mt-3">
                        {{ $corridors->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection
