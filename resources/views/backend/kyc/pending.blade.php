@php
    use App\Enums\KycStatus;
@endphp
@extends('backend.layouts.app')
@section('title', __('KYC Pending Requests'))

@section('content')
    @php
        $pageRequests   = $kycPendingRequests->getCollection();
        $todayCount     = $pageRequests->filter(fn ($r): bool => $r->created_at?->isToday())->count();
        $weekCount      = $pageRequests->filter(fn ($r): bool => $r->created_at?->isCurrentWeek())->count();
        $oldestPending  = $pageRequests->min('created_at');
    @endphp

    <div class="admin-users-page admin-kyc-page">
        {{-- Hero --}}
        <section class="admin-users-hero">
            <div class="admin-users-hero__copy">
                <h1 class="admin-users-hero__title">{{ __('KYC Pending Requests') }}</h1>
                <p class="admin-users-hero__subtitle">{{ __('Work through the queue: review submissions, approve trusted users, and keep new sign-ups moving.') }}</p>
            </div>
        </section>

        {{-- KPI strip --}}
        <div class="admin-users-stats" aria-label="{{ __('Pending KYC summary') }}">
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--warning">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ number_format($kycPendingRequests->total()) }}</span>
                    <span class="admin-users-stat__label">{{ __('Total pending') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--primary">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $todayCount }}</span>
                    <span class="admin-users-stat__label">{{ __('Submitted today') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--info">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $weekCount }}</span>
                    <span class="admin-users-stat__label">{{ __('This week') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--success">
                    <i class="fa-solid fa-stopwatch" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $oldestPending ? $oldestPending->diffForHumans(null, true) : '—' }}</span>
                    <span class="admin-users-stat__label">{{ __('Oldest waiting') }}</span>
                </div>
            </div>
        </div>

        {{-- Panel: filters + table --}}
        <section class="admin-users-panel">
            <div class="admin-users-filterbar">
                <form action="{{ route('admin.kyc.pending') }}" method="GET" class="admin-users-filters row g-2 g-xl-3 align-items-end">
                    {{-- Date range --}}
                    <div class="admin-users-filter col-12 col-md-6 col-xl-auto">
                        <label class="form-label" for="reportrange">{{ __('Date Range') }}</label>
                        <input type="hidden" name="daterange" value="{{ request('daterange') }}">
                        <div id="reportrange" class="form-control admin-kyc-daterange">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <span></span>
                            <i class="fa-solid fa-angle-down ms-2" aria-hidden="true"></i>
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="admin-users-filter admin-users-filter--search col-12 col-xl">
                        <label class="visually-hidden" for="admin-kyc-pending-search">{{ __('Search') }}</label>
                        <div class="admin-users-search">
                            <i class="fa-solid fa-magnifying-glass admin-users-search__icon" aria-hidden="true"></i>
                            <input type="text"
                                   id="admin-kyc-pending-search"
                                   name="search"
                                   value="{{ request('search') }}"
                                   class="form-control admin-users-search__input"
                                   placeholder="{{ __('Search by user name, email or KYC type') }}"
                                   aria-label="{{ __('Search') }}">
                            <button type="submit" class="admin-users-search__submit" aria-label="{{ __('Search') }}">
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                            @if(request()->query())
                                <a href="{{ route('admin.kyc.pending') }}" class="admin-users-search__reset">
                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                    <span>{{ __('Reset') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="admin-users-table-wrap">
                @if($kycPendingRequests->isNotEmpty())
                    <table class="table admin-users-table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Member') }}</th>
                                <th>{{ __('KYC Submission') }}</th>
                                <th>{{ __('Submitted') }}</th>
                                @can('kyc-action')
                                    <th class="text-end">{{ __('Action') }}</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kycPendingRequests as $submission)
                                @php
                                    $user       = $submission->user;
                                    $avatarData = getUserAvatarDetails($user->first_name, $user->last_name);
                                    $kycType    = $submission->kycTemplate?->title ?? __('Unknown template');
                                @endphp
                                <tr class="admin-users-row align-middle">
                                    {{-- Member --}}
                                    <td>
                                        <div class="admin-users-person">
                                            <div class="admin-users-avatar-wrap">
                                                @if($user->avatar)
                                                    <img class="admin-users-avatar" src="{{ asset($user->avatar) }}"
                                                         alt="{{ $user->name }}" loading="lazy">
                                                @else
                                                    <div class="admin-users-avatar {{ $avatarData['class'] }} text-white">
                                                        {{ $avatarData['initials'] }}
                                                    </div>
                                                @endif
                                                <span class="admin-users-status-dot bg-{{ $user->status->color() }}"
                                                      title="{{ $user->status->label() }}"></span>
                                            </div>
                                            <div class="admin-users-person__copy">
                                                <a href="{{ route('admin.user.manage', ['username' => $user->username, 'param' => 'statistics']) }}"
                                                   class="admin-users-name">
                                                    {{ title($user->name) }}
                                                </a>
                                                <div class="admin-users-username">
                                                    <span>{{ '@'.$user->username }}</span>
                                                    <span class="admin-user-role-badge admin-user-role-badge--{{ $user->role->value }}">
                                                        {{ $user->role->title() }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- KYC Submission --}}
                                    <td>
                                        <div class="admin-users-doc">
                                            <span class="admin-users-doc__label">{{ $kycType }}</span>
                                            <div class="admin-users-doc__badges">
                                                <span class="admin-users-pill admin-users-pill--warning">
                                                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                                                    {{ __('Awaiting review') }}
                                                </span>
                                                <span class="admin-users-pill admin-users-pill--{{ $user->email_verified_at ? 'success' : 'danger' }}">
                                                    <i class="fa-solid {{ $user->email_verified_at ? 'fa-circle-check' : 'fa-circle-xmark' }}" aria-hidden="true"></i>
                                                    {{ $user->email_verified_at ? __('Email verified') : __('Email unverified') }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Submitted --}}
                                    <td>
                                        <div class="admin-users-date">
                                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                            <div>
                                                <span>{{ $submission->created_at->format('Y-m-d H:i') }}</span>
                                                <small>{{ $submission->created_at->diffForHumans() }}</small>
                                            </div>
                                        </div>
                                    </td>

                                    @can('kyc-action')
                                        <td class="text-end">
                                            <button type="button"
                                                    class="admin-users-action"
                                                    data-coreui-toggle="modal"
                                                    data-coreui-target="#review-{{ $submission->id }}">
                                                <i class="fa-solid fa-gavel" aria-hidden="true"></i>
                                                <span>{{ __('Review') }}</span>
                                            </button>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="admin-users-pagination">
                        {{ $kycPendingRequests->links() }}
                    </div>
                @else
                    <div class="admin-users-empty">
                        <x-admin-not-found
                            :title="__('No pending KYC requests')"
                            :message="__('Pending submissions will appear here when users submit verification.')"
                            icon="fa-id-card"
                        />
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Review modals (rendered outside the table to avoid inheriting cell text-alignment) --}}
    @can('kyc-action')
        @foreach($kycPendingRequests as $submission)
            @include('backend.kyc.partials._review_modal')
        @endforeach
    @endcan
@endsection
