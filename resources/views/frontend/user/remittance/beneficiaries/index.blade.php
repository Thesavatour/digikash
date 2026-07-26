@extends('frontend.layouts.user.index')
@section('title', __('Recipients'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/remittance.css?v=' . filemtime(public_path('frontend/css/remittance.css'))) }}">
@endpush

@section('content')
    @php
        $tones = ['indigo','rose','amber','teal','violet','sky'];
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="single-form-card">
                @include('frontend.user.remittance.partials._page_header', [
                    'rmtPageTitle'    => __('Recipients'),
                    'rmtPageSubtitle' => __('Save bank accounts and mobile wallets to send abroad in seconds.'),
                    'rmtPageIcon'     => 'fas fa-users',
                    'rmtCurrentPage'  => 'beneficiaries',
                ])
            </div>
        </div>
    </div>

    <div class="dk-rmt">
        <div class="d-flex justify-content-end mb-3">
            <a class="dk-rmt__btn dk-rmt__btn--primary" href="{{ route('user.beneficiaries.create') }}">
                <i class="fa-solid fa-user-plus"></i> {{ __('Add recipient') }}
            </a>
        </div>

        {{-- Search --}}
        <form method="GET" class="dk-rmt__card mb-3" style="padding:14px;">
            <div style="position:relative;">
                <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--dk-rmt-muted);font-size:13px;pointer-events:none;"></i>
                <input type="text" name="search" class="dk-rmt__input" style="padding-left:38px;padding-right:38px;"
                       value="{{ $search }}"
                       placeholder="{{ __('Search by name, phone, country, or currency...') }}">
                @if ($search)
                    <a href="{{ route('user.beneficiaries.index') }}"
                       style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--dk-rmt-muted);">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                @endif
            </div>
        </form>

        {{-- Grid --}}
        @if ($beneficiaries->isEmpty())
            @if ($search)
                <x-user-not-found
                    :title="__('No recipients match your search')"
                    :message="__('Try a different search term or clear the filter.')"
                    icon="fa-user-plus"
                    :action-url="route('user.beneficiaries.index')"
                    :action-label="__('Clear search')"
                    action-icon="fa-rotate-left"
                />
            @else
                <x-user-not-found
                    :title="__('No recipients yet')"
                    :message="__('Add a recipient to start sending money to family or friends abroad.')"
                    icon="fa-user-plus"
                    :action-url="route('user.beneficiaries.create')"
                    :action-label="__('Add your first recipient')"
                    action-icon="fa-plus"
                />
            @endif
        @else
            <div class="dk-rmt__people">
                @foreach ($beneficiaries as $i => $beneficiary)
                    @php
                        $tone = $tones[$i % count($tones)];
                        $initials = collect(explode(' ', $beneficiary->full_name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                    @endphp
                    <div class="dk-rmt__person">
                        <div class="dk-rmt__person-head">
                            <div style="position:relative;">
                                <span class="dk-rmt__avatar dk-rmt__avatar--lg dk-rmt__avatar--{{ $tone }}">{{ $initials }}</span>
                                <span class="dk-rmt__recipient-flag">{{ getCountryFlagEmoji($beneficiary->country_code) }}</span>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="dk-rmt__person-name">
                                    {{ $beneficiary->full_name }}
                                    @if ($beneficiary->is_favorite)
                                        <i class="fa-solid fa-star dk-rmt__star"></i>
                                    @endif
                                </div>
                                @if ($beneficiary->nickname)
                                    <div class="dk-rmt__person-nick">"{{ $beneficiary->nickname }}"</div>
                                @endif
                            </div>
                        </div>

                        <div class="dk-rmt__person-row">
                            <i class="{{ $beneficiary->payout_method->icon() }}"></i>
                            <span>{{ $beneficiary->payout_method->label() }}</span>
                        </div>
                        <div class="dk-rmt__person-row">
                            <i class="fa-solid fa-location-dot"></i>
                            <span>{{ $beneficiary->country_code }} · {{ $beneficiary->currency }}</span>
                        </div>
                        <div class="dk-rmt__person-row dk-mono" style="font-size:12px;">
                            <i class="fa-solid fa-credit-card"></i>
                            <span>{{ $beneficiary->masked_account }}</span>
                        </div>

                        <div class="dk-rmt__person-actions">
                            <a class="dk-rmt__btn dk-rmt__btn--primary" style="flex:1;"
                               href="{{ route('user.remittance.create', ['beneficiary_id' => $beneficiary->id]) }}">
                                <i class="fa-solid fa-paper-plane"></i> {{ __('Send') }}
                            </a>
                            <a class="dk-rmt__btn dk-rmt__btn--ghost"
                               style="background:var(--dk-rmt-soft);border:1px solid var(--dk-rmt-border);width:44px;padding:0;"
                               href="{{ route('user.beneficiaries.edit', $beneficiary) }}"
                               title="{{ __('Edit') }}">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <form action="{{ route('user.beneficiaries.destroy', $beneficiary) }}"
                                  method="POST"
                                  style="margin:0;"
                                  onsubmit="return confirm('{{ __('Remove this recipient?') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="dk-rmt__btn dk-rmt__btn--danger"
                                        style="width:44px;padding:0;"
                                        title="{{ __('Remove') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
