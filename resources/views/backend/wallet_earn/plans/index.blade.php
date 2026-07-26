@extends('backend.wallet_earn.layout')

@section('title', __('Wallet Earn Plans'))
@section('wallet_earn_title', __('Wallet Earn Plans'))
@section('wallet_earn_icon', 'apps')
@section('wallet_earn_subtitle', __('Create and manage plan limits, reward rates, durations, and approval behavior.'))

@section('wallet_earn_action')
    @can('wallet-earn-manage')
        <a href="{{ route('admin.wallet-earn.plans.create') }}" class="btn btn-primary d-flex align-items-center px-4">
            <x-icon name="add" height="20" width="20" class="me-2"/>
            @lang('Add Plan')
        </a>
    @endcan
@endsection

@section('wallet_earn_content')
    @php
        $activePlans = $plans->where('status', true)->count();
        $highlightedPlans = $plans->filter(fn ($plan): bool => $plan->isHighlighted())->count();
        $totalStakes = $plans->sum('stakes_count');
    @endphp

    <div class="admin-users-page">
        <div class="admin-users-stats" aria-label="{{ __('Wallet Earn plan summary') }}">
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--primary">
                    <x-icon name="apps" height="18" width="18"/>
                </span>
                <div>
                    <span class="admin-users-stat__label">@lang('Total Plans')</span>
                    <span class="admin-users-stat__value">{{ number_format($plans->count()) }}</span>
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
                    <span class="admin-users-stat__label">@lang('Highlighted')</span>
                    <span class="admin-users-stat__value">{{ number_format($highlightedPlans) }}</span>
                </div>
            </div>
            <div class="admin-users-stat">
                <span class="admin-users-stat__icon admin-users-stat__icon--info">
                    <x-icon name="people" height="18" width="18"/>
                </span>
                <div>
                    <span class="admin-users-stat__label">@lang('Total Stakes')</span>
                    <span class="admin-users-stat__value">{{ number_format($totalStakes) }}</span>
                </div>
            </div>
        </div>

        <section class="admin-users-panel">
            <div class="admin-users-table-wrap">
                @if($plans->isNotEmpty())
                    <table class="table admin-users-table admin-users-table--wide mb-0">
                        <thead>
                        <tr>
                            @can('wallet-earn-manage')
                                <th class="admin-users-drag-col text-center">&nbsp;</th>
                            @endcan
                            <th>@lang('Plan')</th>
                            <th>@lang('Currency')</th>
                            <th>@lang('Plan Badge')</th>
                            <th>@lang('Limit')</th>
                            <th>@lang('Profit')</th>
                            <th>@lang('Duration')</th>
                            <th>@lang('Payout')</th>
                            <th>@lang('Status')</th>
                            @can('wallet-earn-manage')
                                <th class="text-end">@lang('Action')</th>
                            @endcan
                        </tr>
                        </thead>
                        <tbody
                            id="wallet-earn-plan-sortable"
                            data-position-url="{{ route('admin.wallet-earn.plans.position-update') }}"
                            data-csrf-token="{{ csrf_token() }}"
                            data-success-message="{{ __('Plan order updated successfully.') }}"
                            data-error-message="{{ __('Unable to update plan order right now.') }}"
                        >
                        @foreach($plans as $plan)
                            <tr class="admin-users-row align-middle" data-id="{{ $plan->id }}">
                                @can('wallet-earn-manage')
                                    <td class="text-center">
                                        <i class="fa-solid fa-grip-vertical admin-users-drag-handle drag-handle" title="@lang('Drag to sort')" data-coreui-toggle="tooltip"></i>
                                    </td>
                                @endcan
                                <td>
                                    <div class="admin-users-doc">
                                        <span class="admin-users-name">{{ $plan->name }}</span>
                                        <span class="admin-users-meta">#{{ $plan->id }} &middot; {{ $plan->stakes_count }} @lang('stakes')</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="admin-users-pill admin-users-pill--primary">
                                        {{ $plan->currency_id ? $plan->currency->code : __('All Currencies') }}
                                    </span>
                                </td>
                                <td>
                                    @if($plan->isHighlighted())
                                        <div class="admin-users-stack">
                                            <span class="admin-users-pill admin-users-pill--warning">{{ $plan->is_featured ? __('Featured Plan') : __('Badge Active') }}</span>
                                            <span class="admin-users-meta">{{ $plan->planBadgeLabel() }}</span>
                                        </div>
                                    @else
                                        <span class="admin-users-meta">@lang('Normal')</span>
                                    @endif
                                </td>
                                <td>{{ $plan->amountRangeLabel() }}</td>
                                <td>
                                    <strong>{{ number_format((float) $plan->profit_rate, 4) }}</strong>
                                    <span class="admin-users-meta">{{ $plan->profit_type->label() }}</span>
                                </td>
                                <td>{{ $plan->durationLabel() }}</td>
                                <td>{{ $plan->payout_frequency->label() }}</td>
                                <td>
                                    <span class="admin-users-pill {{ $plan->status ? 'admin-users-pill--success' : 'admin-users-pill--danger' }}">
                                        {{ $plan->status ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                @can('wallet-earn-manage')
                                    <td class="text-end">
                                        <div class="admin-users-actions">
                                            <a href="{{ route('admin.wallet-earn.plans.edit', $plan) }}" class="admin-users-action">
                                                <x-icon name="manage" height="14" width="14"/>
                                                @lang('Edit')
                                            </a>
                                            <a href="javascript:void(0)" class="admin-users-action admin-users-action--danger delete" data-url="{{ route('admin.wallet-earn.plans.destroy', $plan) }}">
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
                            :title="__('No Wallet Earn plans found')"
                            :message="__('Create earn plans to let users stake wallet balances.')"
                            icon="fa-chart-line"
                        />
                    </div>
                @endif
            </div>
        </section>
    </div>
@endsection

@can('wallet-earn-manage')
    @push('scripts')
        <script src="{{ asset('backend/js/wallet-earn-admin.js?v=' . config('app.version')) }}"></script>
    @endpush
@endcan
