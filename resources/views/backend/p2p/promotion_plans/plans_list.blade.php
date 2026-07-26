@extends('backend.p2p.layout')

@section('title', __('Promotion Plans'))

@section('p2p_title')
    {{ __('Promotion Plans') }}
@endsection

@section('p2p_icon', 'apps')

@section('p2p_subtitle', __('Create and manage promotion plans, pricing, billing types, durations, and visibility.'))

@section('p2p_action')
    @can('p2p-manage')
        <a href="{{ route('admin.p2p.promotion-packages.create') }}" class="btn btn-primary d-flex align-items-center px-4">
            <x-icon name="add" height="20" width="20" class="me-2"/>
            @lang('Add Plan')
        </a>
    @endcan
@endsection

@section('p2p_content')
    @php
        $decimals     = (int) setting('site_decimal', 2);
        $planCount    = $packages->count();
        $activePlans  = $packages->where('status', true)->count();
        $featuredId   = optional($packages->firstWhere(fn ($p) => strtoupper((string) ($p->visibility ?? '')) === 'FEATURED' && $p->status))->id;
        $featuredCount = $packages->filter(fn ($p) => strtoupper((string) ($p->visibility ?? '')) === 'FEATURED' && $p->status)->count();
        $freePlans    = $packages->filter(fn ($p) => (float) $p->effectiveBasePrice() <= 0)->count();
    @endphp

    <div class="admin-users-page">
        <div class="admin-users-stats" aria-label="{{ __('Promotion plan summary') }}">
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--primary">
                    <x-icon name="apps" height="18" width="18"/>
                </span>
                <div>
                    <span class="admin-users-stat__label">@lang('Total Plans')</span>
                    <span class="admin-users-stat__value">{{ number_format($planCount) }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--success">
                    <x-icon name="check" height="18" width="18"/>
                </span>
                <div>
                    <span class="admin-users-stat__label">@lang('Active Plans')</span>
                    <span class="admin-users-stat__value">{{ number_format($activePlans) }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--warning">
                    <x-icon name="star" height="18" width="18"/>
                </span>
                <div>
                    <span class="admin-users-stat__label">@lang('Featured')</span>
                    <span class="admin-users-stat__value">{{ number_format($featuredCount) }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--info">
                    <x-icon name="currency" height="18" width="18"/>
                </span>
                <div>
                    <span class="admin-users-stat__label">@lang('Free Plans')</span>
                    <span class="admin-users-stat__value">{{ number_format($freePlans) }}</span>
                </div>
            </div>
        </div>

        <section class="admin-users-panel">
            <div class="admin-users-table-wrap">
                @if($packages->isNotEmpty())
                    <table class="table admin-users-table admin-users-table--wide mb-0">
                        <thead>
                        <tr>
                            @can('p2p-manage')
                                <th class="admin-users-drag-col text-center">&nbsp;</th>
                            @endcan
                            <th>@lang('Plan')</th>
                            <th>@lang('Billing')</th>
                            <th>@lang('Price')</th>
                            <th>@lang('Duration')</th>
                            <th>@lang('Visibility')</th>
                            <th>@lang('Status')</th>
                            @can('p2p-manage')
                                <th class="text-end">@lang('Action')</th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody
                            id="p2p-package-sortable"
                            data-position-url="{{ route('admin.p2p.promotion-packages.position-update') }}"
                            data-csrf-token="{{ csrf_token() }}"
                            data-success-message="{{ __('Package order updated successfully.') }}"
                            data-error-message="{{ __('Unable to update package order right now.') }}"
                        >
                        @foreach($packages as $package)
                            @php
                                $billingType = strtoupper(trim((string) ($package->billing_type ?? 'FIXED')));
                                $billingText = match ($billingType) {
                                    'DAILY_PRICE'   => __('Daily'),
                                    'PER_TRADE_FEE' => __('Per Trade'),
                                    default         => __('Fixed'),
                                };
                                $durationMinutes = (int) $package->duration_minutes;
                                $durationText = $durationMinutes >= 60
                                    ? (rtrim(rtrim(number_format($durationMinutes / 60, 2, '.', ''), '0'), '.') . ' ' . __('hours'))
                                    : ($durationMinutes . ' ' . __('min'));
                                $visibility = strtoupper((string) ($package->visibility ?? 'PUBLIC'));
                                $isFeatured = $package->id === $featuredId;
                                $basePrice  = (float) $package->effectiveBasePrice();
                            @endphp
                            <tr class="admin-users-row align-middle" data-id="{{ $package->id }}">
                                @can('p2p-manage')
                                    <td class="text-center">
                                        <i class="fa-solid fa-grip-vertical admin-users-drag-handle drag-handle" title="@lang('Drag to sort')" data-coreui-toggle="tooltip"></i>
                                    </td>
                                @endcan
                                <td>
                                    <div class="admin-users-doc">
                                        <span class="admin-users-name">
                                            {{ $package->name }}
                                            @if($isFeatured)
                                                <span class="admin-users-pill admin-users-pill--warning">@lang('Featured')</span>
                                            @endif
                                        </span>
                                        <span class="admin-users-meta">#{{ $package->id }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-users-pill admin-users-pill--primary">{{ $billingText }}</span>
                                </td>
                                <td>
                                    @if($basePrice > 0)
                                        <strong>{{ siteCurrency('code') }} {{ number_format($basePrice, $decimals) }}</strong>
                                    @else
                                        <span class="admin-users-meta">@lang('Free')</span>
                                    @endif
                                </td>
                                <td>{{ $durationText }}</td>
                                <td>
                                    <span class="admin-users-pill {{ $visibility === 'FEATURED' ? 'admin-users-pill--warning' : 'admin-users-pill--document' }}">
                                        {{ $visibility }}
                                    </span>
                                </td>
                                <td>
                                    <span class="admin-users-pill {{ $package->status ? 'admin-users-pill--success' : 'admin-users-pill--danger' }}">
                                        {{ $package->status ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                @can('p2p-manage')
                                    <td class="text-end">
                                        <div class="admin-users-actions">
                                            <a href="{{ route('admin.p2p.promotion-packages.edit', $package) }}" class="admin-users-action">
                                                <x-icon name="manage" height="14" width="14"/>
                                                @lang('Edit')
                                            </a>
                                            <a href="javascript:void(0)" class="admin-users-action admin-users-action--danger delete" data-url="{{ route('admin.p2p.promotion-packages.destroy', $package) }}">
                                                <x-icon name="delete-3" height="14" width="14"/>
                                                @lang('Delete')
                                            </a>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="admin-users-empty">
                        <x-admin-not-found
                            :title="__('No plans found')"
                            :message="__('Create your first promotion plan to start monetizing trade-ad placements.')"
                            icon="fa-bullhorn"
                        />
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection

@can('p2p-manage')
    @push('scripts')
        @include('backend.p2p.promotion_plans.partials._plans_list_scripts')
    @endpush
@endcan
