@extends('backend.layouts.app')
@section('title', __('Edit Corridor'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('backend/css/remittance.css?v=' . filemtime(public_path('backend/css/remittance.css'))) }}">
@endpush

@section('content')
    <div class="remittance-admin remittance-admin--form">

        <section class="rmt-hero" aria-labelledby="rmt-hero-title">
            <div class="rmt-hero__grid">
                {{-- LEFT: identity + meta --}}
                <div class="rmt-hero__main">
                    <div class="rmt-hero__head">
                        <div class="rmt-pagehead__icon rmt-pagehead__icon--gradient" aria-hidden="true">
                            <i class="fa-solid fa-route"></i>
                        </div>

                        <div class="rmt-hero__head-body">
                            <div class="rmt-pagehead__topline">
                                <span class="rmt-pagehead__eyebrow">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                    {{ __('Edit Corridor') }}
                                </span>
                                @if ($corridor->status)
                                    <span class="rmt-pagehead__pill rmt-pagehead__pill--success">
                                        <span class="rmt-pagehead__pill-dot"></span> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="rmt-pagehead__pill rmt-pagehead__pill--muted">
                                        <span class="rmt-pagehead__pill-dot"></span> {{ __('Disabled') }}
                                    </span>
                                @endif
                            </div>

                            <h1 id="rmt-hero-title" class="rmt-hero__title">{{ $corridor->name }}</h1>
                        </div>
                    </div>

                    {{-- Boxed meta strip --}}
                    <div class="rmt-hero__metabox">
                        <span class="rmt-hero__meta rmt-hero__meta--route">
                            <span class="rmt-hero__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-right-left"></i>
                            </span>
                            <code class="rmt-hero__code">{{ $corridor->source_country }}/{{ $corridor->source_currency }}</code>
                            <span class="rmt-hero__meta-arrow" aria-hidden="true">
                                <i class="fa-solid fa-arrow-right"></i>
                            </span>
                            <code class="rmt-hero__code">{{ $corridor->destination_country }}/{{ $corridor->destination_currency }}</code>
                        </span>

                        <span class="rmt-hero__meta rmt-hero__meta--driver">
                            <span class="rmt-hero__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-plug"></i>
                            </span>
                            <span class="rmt-hero__meta-stack">
                                <span class="rmt-hero__meta-label">{{ __('Driver') }}</span>
                                <code class="rmt-hero__code rmt-hero__code--neutral">{{ $corridor->payout_gateway }}</code>
                            </span>
                        </span>

                        <span class="rmt-hero__meta rmt-hero__meta--transfers">
                            <span class="rmt-hero__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-paper-plane"></i>
                            </span>
                            <span class="rmt-hero__meta-stack">
                                <span class="rmt-hero__meta-label">{{ __('Transfers') }}</span>
                                <strong class="rmt-hero__meta-value">{{ number_format($corridor->transfers_count) }}</strong>
                            </span>
                        </span>
                    </div>
                </div>

                {{-- RIGHT: breadcrumb (top) + decorative globe (below) --}}
                <div class="rmt-hero__side">
                    <nav class="rmt-crumbs rmt-crumbs--inline" aria-label="{{ __('Breadcrumb') }}">
                        <a href="{{ route('admin.remittance.corridors.index') }}" class="rmt-crumbs__link">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                            <span>{{ __('Corridors') }}</span>
                        </a>
                    </nav>

                    <aside class="rmt-hero__globe" aria-hidden="true">
                    <svg class="rmt-hero__globe-svg" viewBox="0 0 1000 500" preserveAspectRatio="xMidYMid meet" focusable="false">
                        <defs>
                            <pattern id="rmt-map-dots-{{ $corridor->id }}" x="0" y="0" width="10" height="10" patternUnits="userSpaceOnUse">
                                <circle cx="2" cy="2" r="1.6" fill="#94a3b8"/>
                            </pattern>
                            <mask id="rmt-map-mask-{{ $corridor->id }}">
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
                            <linearGradient id="rmt-arc-grad-{{ $corridor->id }}" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#4663EE"/>
                                <stop offset="100%" stop-color="#16a34a"/>
                            </linearGradient>
                            <radialGradient id="rmt-pin-blue-{{ $corridor->id }}" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#4663EE" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#4663EE" stop-opacity="0"/>
                            </radialGradient>
                            <radialGradient id="rmt-pin-green-{{ $corridor->id }}" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#16a34a" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#16a34a" stop-opacity="0"/>
                            </radialGradient>
                        </defs>

                        {{-- Soft background --}}
                        <rect x="0" y="0" width="1000" height="500" fill="#F8FAFC" rx="8"/>

                        {{-- Dotted world map (continent silhouettes only) --}}
                        <rect x="0" y="0" width="1000" height="500" fill="url(#rmt-map-dots-{{ $corridor->id }})" mask="url(#rmt-map-mask-{{ $corridor->id }})" opacity="0.55"/>

                        {{-- Routing arc from source to destination --}}
                        <path d="M 294 139 Q 522 35 751 184"
                              stroke="url(#rmt-arc-grad-{{ $corridor->id }})"
                              stroke-width="2.5"
                              fill="none"
                              stroke-linecap="round"
                              stroke-dasharray="6 6"/>

                        {{-- Origin glow + pin --}}
                        <circle cx="294" cy="139" r="22" fill="url(#rmt-pin-blue-{{ $corridor->id }})"/>
                        <circle cx="294" cy="139" r="6" fill="#4663EE"/>
                        <circle cx="294" cy="139" r="3" fill="#ffffff"/>

                        {{-- Destination glow + pin --}}
                        <circle cx="751" cy="184" r="22" fill="url(#rmt-pin-green-{{ $corridor->id }})"/>
                        <circle cx="751" cy="184" r="6" fill="#16a34a"/>
                        <circle cx="751" cy="184" r="3" fill="#ffffff"/>
                    </svg>

                    <span class="rmt-hero__globe-badge rmt-hero__globe-badge--from">
                        {{ $corridor->source_currency }}
                    </span>
                    <span class="rmt-hero__globe-badge rmt-hero__globe-badge--to">
                        {{ $corridor->destination_currency }}
                    </span>
                    </aside>
                </div>
            </div>
        </section>

        @include('backend.remittance.corridors._form', [
            'action'        => route('admin.remittance.corridors.update', $corridor),
            'method'        => 'PUT',
            'corridor'      => $corridor,
            'payoutMethods' => $payoutMethods,
            'countries'     => $countries,
            'currencies'    => $currencies,
            'gateways'      => $gateways,
        ])
    </div>

@endsection
