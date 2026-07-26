@php
    use App\Enums\VirtualCard\CardholderType;
@endphp
@extends('backend.virtual_card.index')
@section('title', __('Cardholder Management'))

@section('virtual_card_header')
    <div class="vc-admin-hero my-3">
        <div>
            <span class="vc-admin-hero__eyebrow">{{ __('Identity Registry') }}</span>
            <h3>{{ __('Cardholder Management') }}</h3>
            <p>{{ __('Review personal and business cardholders, KYC state, and provider compatibility.') }}</p>
        </div>
        <div class="vc-admin-hero__stats">
            <div>
                <span>{{ __('Cardholders') }}</span>
                <strong>{{ $cardholders->total() }}</strong>
            </div>
            <div>
                <span>{{ __('Active Providers') }}</span>
                <strong>{{ $providers->count() }}</strong>
            </div>
        </div>
    </div>
@endsection

@section('virtual_card_content')
    <div class="card-body vc-admin-board">
        <div class="vc-admin-toolbar">
            <form action="{{ route('admin.virtual-card.cardholders.index') }}" method="GET" class="vc-admin-filter-form">
                <div class="vc-admin-filter-form__status">
                    <x-form.select name="status" :options="$statuses ?? []" :selected="request('status')" :includeBlank="true"/>
                </div>
                <div class="vc-admin-filter-form__date">
                    <div class="input-group">
                        <input type="hidden" name="daterange" value="{{ request('daterange') }}">
                        <div id="reportrange" class="form-control d-flex align-items-center justify-content-between">
                            <span class="d-flex align-items-center gap-2 text-nowrap">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span class="flex-grow-1"></span>
                            </span>
                            <x-icon name="angle-down" class="text-muted flex-shrink-0"/>
                        </div>
                    </div>
                </div>
                <div class="vc-admin-filter-form__search">
                    <div class="input-group">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search by user, email, business, ID number...') }}">
                        <button type="submit" class="btn btn-primary" aria-label="{{ __('Search cardholders') }}"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive vc-admin-table vc-admin-table--cardholders">
            <table class="table align-middle mb-0 vc-admin-table__table">
                <thead>
                <tr>
                    <th class="vc-admin-table__cell--user">{{ __('User') }}</th>
                    <th class="vc-admin-table__cell--cardholder">{{ __('Cardholder') }}</th>
                    <th class="vc-admin-table__cell--identity">{{ __('Identity / ID') }}</th>
                    <th class="vc-admin-table__cell--providers">{{ __('Compatible Providers') }}</th>
                    <th class="vc-admin-table__cell--status">{{ __('Status') }}</th>
                    <th class="vc-admin-table__cell--requested">{{ __('Requested') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($cardholders as $holder)
                    @php
                        $isBusiness  = $holder->card_type instanceof CardholderType
                            && $holder->card_type === CardholderType::BUSINESS;
                        $displayName = $isBusiness && $holder->business
                            ? $holder->business->business_name
                            : $holder->full_name;
                        $chCountry   = $isBusiness && $holder->business
                            ? $holder->business->country
                            : $holder->country;
                        $compat      = $providers->filter(fn ($p) => $p->supportsCountry($chCountry));
                    @endphp
                    <tr>
                        <td class="vc-admin-table__cell--user">
                            <div class="vc-admin-user">
                                <img src="{{ $holder->user->avatar_alt }}" alt="{{ $holder->user->name ?? '-' }}" loading="lazy">
                                <div>
                                    <a href="{{ route('admin.user.manage', $holder->user->username) }}">{{ $holder->user->name }}</a>
                                    <span class="badge bg-{{ $holder->user->role->color() }}">{{ $holder->user->role->title() }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="vc-admin-table__cell--cardholder">
                            <div class="fw-semibold">
                                {{ $displayName ?: '—' }}
                                <span class="badge bg-{{ $holder->card_type->class() }} ms-1">{{ $holder->card_type->label() }}</span>
                            </div>
                            <div class="small text-muted">{{ $holder->email ?? $holder->user->email ?? '-' }}</div>
                            @if($isBusiness && $holder->business?->beneficial_owners)
                                <div class="small text-muted mt-1">
                                    <i class="fa-solid fa-user-tie"></i>
                                    {{ trans_choice(':count UBO|:count UBOs', count($holder->business->beneficial_owners), ['count' => count($holder->business->beneficial_owners)]) }}
                                </div>
                            @endif
                        </td>
                        <td class="vc-admin-table__cell--identity">
                            <div class="d-flex flex-wrap gap-1">
                                @if($holder->id_type)
                                    <span class="badge bg-light text-dark border">
                                        <i class="fa-solid fa-id-card-clip me-1"></i>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $holder->id_type)) }}
                                    </span>
                                @endif
                                @if($holder->id_number)
                                    <span class="badge bg-light text-dark border font-monospace">{{ $holder->id_number }}</span>
                                @endif
                                @if($chCountry)
                                    <span class="badge bg-info text-white">
                                        <i class="fa-solid fa-flag me-1"></i>{{ $chCountry }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="vc-admin-table__cell--providers">
                            @if($compat->isEmpty())
                                <span class="badge bg-danger">
                                    <i class="fa-solid fa-ban me-1"></i>{{ __('None') }}
                                </span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($compat as $provider)
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            {{ $provider->display_label ?: $provider->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="vc-admin-table__cell--status"><span class="badge bg-{{ $holder->status->badgeColor() }}">{{ $holder->status->label() }}</span></td>
                        <td class="vc-admin-table__cell--requested">
                            <div class="fw-semibold">{{ $holder->created_at->format('M d, Y') }}</div>
                            <small class="text-muted">{{ $holder->created_at->diffForHumans() }}</small>
                        </td>
                        <td class="text-end vc-admin-table__cell--actions">
                            <button class="btn btn-primary vc-admin-action" data-coreui-toggle="modal" data-coreui-target="#view-cardholder-{{ $holder->id }}">
                                <i class="fa-solid fa-id-card"></i>
                                {{ $holder->status->isPending() ? __('Manage Request') : __('Details') }}
                            </button>
                            @include('backend.virtual_card.cardholder.partials._view_modal', ['holder' => $holder, 'providers' => $providers])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-admin-not-found
                                :title="__('No cardholders found')"
                                :message="__('Cardholder requests matching the current filters will appear here.')"
                                icon="fa-id-card"
                            />
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($cardholders->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $cardholders->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endsection
