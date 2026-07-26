@php
    use App\Enums\KycStatus;
@endphp
@extends('backend.layouts.app')
@section('title', __('All KYC'))

@section('content')
    @php
        $pageRequests = $kycRequests->getCollection();
        $approvedCount = $pageRequests->filter(fn ($r): bool => $r->status === KycStatus::APPROVED)->count();
        $pendingCount = $pageRequests->filter(fn ($r): bool => $r->status === KycStatus::PENDING)->count();
        $rejectedCount = $pageRequests->filter(fn ($r): bool => $r->status === KycStatus::REJECTED)->count();
    @endphp

    <div class="admin-users-page admin-kyc-page">
        {{-- Hero --}}
        <section class="admin-users-hero">
            <div class="admin-users-hero__copy">
                <h1 class="admin-users-hero__title">{{ __('KYC Requests') }}</h1>
                <p class="admin-users-hero__subtitle">{{ __('Review identity submissions, approve trusted users, and keep your compliance queue clear.') }}</p>
            </div>
        </section>

        {{-- KPI strip --}}
        <div class="admin-users-stats" aria-label="{{ __('KYC summary') }}">
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--primary">
                    <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ number_format($kycRequests->total()) }}</span>
                    <span class="admin-users-stat__label">{{ __('Total submissions') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--warning">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $pendingCount }}</span>
                    <span class="admin-users-stat__label">{{ __('Pending on page') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--success">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $approvedCount }}</span>
                    <span class="admin-users-stat__label">{{ __('Approved on page') }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--info">
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                </span>
                <div>
                    <span class="admin-users-stat__value">{{ $rejectedCount }}</span>
                    <span class="admin-users-stat__label">{{ __('Rejected on page') }}</span>
                </div>
            </div>
        </div>

        {{-- Panel: filters + table --}}
        <section class="admin-users-panel">
            <div class="admin-users-filterbar">
                <form action="{{ route('admin.kyc.index') }}" method="GET" class="admin-users-filters row g-2 g-xl-3 align-items-end">
                    {{-- KYC Status filter --}}
                    <div class="admin-users-filter col-12 col-md-6 col-xl-auto">
                        <x-form.select
                            name="kyc_status"
                            :label="__('KYC Status')"
                            class="form-select pe-5"
                            :options="\App\Enums\KycStatus::options()"
                            :selected="request('kyc_status', 'all')"
                        />
                    </div>

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
                        <label class="visually-hidden" for="admin-kyc-search">{{ __('Search') }}</label>
                        <div class="admin-users-search">
                            <i class="fa-solid fa-magnifying-glass admin-users-search__icon" aria-hidden="true"></i>
                            <input type="text"
                                   id="admin-kyc-search"
                                   name="search"
                                   value="{{ request('search') }}"
                                   class="form-control admin-users-search__input"
                                   placeholder="{{ __('Search by user name, email or KYC type') }}"
                                   aria-label="{{ __('Search') }}">
                            <button type="submit" class="admin-users-search__submit" aria-label="{{ __('Search') }}">
                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            </button>
                            @if(request()->query())
                                <a href="{{ route('admin.kyc.index') }}" class="admin-users-search__reset">
                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                    <span>{{ __('Reset') }}</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <div class="admin-users-table-wrap">
                @if($kycRequests->isNotEmpty())
                    <table class="table admin-users-table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Member') }}</th>
                                <th>{{ __('KYC Submission') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Submitted') }}</th>
                                @can('kyc-action')
                                    <th class="text-end">{{ __('Action') }}</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kycRequests as $submission)
                                @php
                                    $user = $submission->user;
                                    $avatarData = getUserAvatarDetails($user->first_name, $user->last_name);
                                    $statusEnum = $submission->status;
                                    $statusTone = $statusEnum?->color() ?? 'secondary';
                                    $statusLabel = $statusEnum?->label() ?? __('Unknown');
                                    $kycType = $submission->kycTemplate?->title ?? __('Unknown template');
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
                                                <span class="admin-users-pill admin-users-pill--document">
                                                    <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                                                    {{ __('Document') }}
                                                </span>
                                                <span class="admin-users-pill admin-users-pill--{{ $user->email_verified_at ? 'success' : 'danger' }}">
                                                    <i class="fa-solid {{ $user->email_verified_at ? 'fa-circle-check' : 'fa-circle-xmark' }}" aria-hidden="true"></i>
                                                    {{ $user->email_verified_at ? __('Email verified') : __('Email unverified') }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        <span class="admin-users-pill admin-users-pill--{{ $statusTone }}">
                                            @if($statusEnum === KycStatus::APPROVED)
                                                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                            @elseif($statusEnum === KycStatus::PENDING)
                                                <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                                            @elseif($statusEnum === KycStatus::REJECTED)
                                                <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                                            @else
                                                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                            @endif
                                            {{ $statusLabel }}
                                        </span>
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
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                                <span>{{ __('Review') }}</span>
                                            </button>

                                            @include('backend.kyc.partials._review_modal', ['action' => 'view'])
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="admin-users-pagination">
                        {{ $kycRequests->links() }}
                    </div>
                @else
                    <div class="admin-users-empty">
                        <x-admin-not-found
                            :title="__('No KYC requests found')"
                            :message="__('KYC submissions matching the current filters will appear here.')"
                            icon="fa-id-card"
                        />
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection
