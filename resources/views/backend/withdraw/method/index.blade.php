@extends('backend.withdraw.index')
@section('title', $type->label().' '. __('Methods'))

@section('withdraw_header')
    <x-admin-feature-header
        :title="$type->label().' '.__('Withdraw Methods')"
        :subtitle="$type->isAutomatic() ? __('Manage all automatic withdraw methods. These methods are processed instantly and require no manual intervention.') : __('Manage all manual withdraw methods. These require admin review and approval before processing.')"
        icon="withdraw"
    >
        @can('withdraw-method-manage')
            <x-slot:actions>
                @if($type->isAutomatic())
                    <a href="{{ route('admin.payment.gateway.index') }}" class="btn btn-outline-dark d-flex align-items-center px-4">
                        <x-icon name="payment" height="20" width="20" class="me-2"/> <span>{{ __('Payment Gateways') }}</span>
                    </a>
                @endif
                <a href="#new_payment_method_modal" data-coreui-toggle="modal" class="btn btn-primary d-flex align-items-center px-4">
                    <x-icon name="add" height="20" width="20" class="me-2"/> <span class="fw-semibold">{{ __('Create').' '. $type->label().' ' . __('Method') }}</span>
                </a>
            </x-slot:actions>
        @endcan
    </x-admin-feature-header>
@endSection

@section('withdraw_content')
    <div class="card border-0 mb-4">
        <div class="card-body">
            <div class="table-responsive rounded">
                <table class="table mb-0 caption-top">
                    <thead>
                    <tr>
                        <th>{{ __('Logo') }}</th>
                        <th>{{ __('Name | Currency') }}</th>
                        <th>{{ __('Min | Max Withdraw') }}</th>
                        <th>{{ __('Rate Type | Rate') }}</th>
                        <th>{{ __('Charges') }}</th>
                        <th>{{ __('Status') }}</th>
                        @can('withdraw-method-manage')
                            <th>{{ __('Action') }}</th>
                        @endcan
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($paymentMethods as $paymentMethod)
                        <tr class="align-middle">
                            <td>
                                <div class="position-relative me-3">
                                    <img src="{{ asset($paymentMethod->logo) }}" height="20" alt="" loading="lazy">
                                </div>
                            </td>
                            <td>
                                <div class="text-nowrap"><strong class="text-muted">{{ title($paymentMethod->name) }}</strong></div>
                                <div class="small text-body-secondary text-nowrap">
                                    <span class="badge text-bg-secondary">{{ $paymentMethod->currency }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="text-nowrap"><strong class="text-muted">{{ $paymentMethod->currency_symbol }}{{ $paymentMethod->min_withdraw }}</strong></div>
                                <div class="small text-body-secondary text-nowrap"><strong class="text-muted">{{ $paymentMethod->currency_symbol }}{{ $paymentMethod->max_withdraw }}</strong></div>
                            </td>
                            <td>
                                <div class="text-nowrap">
                                    @if($paymentMethod->conversion_rate_live)
                                        <strong class="text-danger">{{ __('LIVE') }}</strong>
                                    @else
                                        <strong class="text-primary">{{ __('LOCAL') }}</strong>
                                    @endif
                                </div>
                                <div class="small text-body-secondary text-nowrap">
                                    <strong>1 {{ siteCurrency() }} = {{ $paymentMethod->conversion_rate }} {{ $paymentMethod->currency }}</strong>
                                </div>
                            </td>
                            <td>
                                <div class="text-nowrap">
                                    <div class="mb-1">
                                        <small class="text-primary fw-semibold"><i class="fas fa-user me-1"></i>User:</small>
                                        <strong class="text-muted">
                                            @if($paymentMethod->user_charge_type && $paymentMethod->user_charge_type->isPercent())
                                                {{ $paymentMethod->user_charge ?? 0 }}%
                                            @else
                                                {{ $paymentMethod->currency_symbol }}{{ $paymentMethod->user_charge ?? 0 }}
                                            @endif
                                        </strong>
                                    </div>
                                    <div>
                                        <small class="text-success fw-semibold"><i class="fas fa-store me-1"></i>Merchant:</small>
                                        <strong class="text-muted">
                                            @if($paymentMethod->merchant_charge_type && $paymentMethod->merchant_charge_type->isPercent())
                                                {{ $paymentMethod->merchant_charge ?? 0 }}%
                                            @else
                                                {{ $paymentMethod->currency_symbol }}{{ $paymentMethod->merchant_charge ?? 0 }}
                                            @endif
                                        </strong>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap text-uppercase">
                                @if($paymentMethod->status)
                                    <span class="badge bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge bg-danger">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            @can('withdraw-method-manage')
                                <td>
                                    @if( (isset($paymentMethod->paymentGateway) && $paymentMethod->paymentGateway->status) || $paymentMethod->type == \App\Enums\MethodType::MANUAL )
                                        <button type="button"
                                                data-edit-url="{{ route('admin.withdraw.method.edit', $paymentMethod->id) }}"
                                                class="btn btn-primary d-flex align-items-center edit-modal">
                                            <x-icon name="manage" height="20"/>
                                            {{ __('Manage') }}
                                        </button>
                                    @else
                                        <button disabled class="btn btn-warning">
                                            <i class="fa-solid fa-lock me-1"></i> {{ __('Disabled by Gateway') }}
                                        </button>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->can('withdraw-method-manage') ? 7 : 6 }}">
                                <x-admin-not-found
                                    :title="__('No withdraw methods found')"
                                    :message="__('Create a withdraw method to let users cash out through it.')"
                                    icon="fa-money-bill-transfer"
                                />
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end mt-3">
                {{ $paymentMethods->links() }}
            </div>
        </div>
    </div>

    @can('withdraw-method-manage')
        @include('backend.withdraw.method.partials._new_payment_method_modal')
        @include('backend.withdraw.method.partials._edit_payment_method_modal')
        @php
            $fields = $paymentMethods->last()?->fields;
            $fieldCount = is_countable($fields) ? count($fields) : 0;
        @endphp
    @endcan
@endsection

@push('scripts')
    @can('withdraw-method-manage')
        @include('backend.withdraw.method.partials._scripts')
    @endcan
@endpush
