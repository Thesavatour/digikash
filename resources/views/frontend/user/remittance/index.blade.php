@extends('frontend.layouts.user.index')
@section('title', __('Transfer History'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/remittance.css?v=' . filemtime(public_path('frontend/css/remittance.css'))) }}">
@endpush

@section('content')
    @php
        use App\Enums\RemittanceStatus;

        $filters = [
            ''           => __('All'),
            'processing' => __('Processing'),
            'submitted'  => __('Submitted'),
            'completed'  => __('Completed'),
            'failed'     => __('Failed'),
        ];

        $statusToPill = [
            RemittanceStatus::Completed->value  => ['cls' => 'success', 'icon' => 'circle-check'],
            RemittanceStatus::Submitted->value  => ['cls' => 'info',    'icon' => 'paper-plane'],
            RemittanceStatus::Processing->value => ['cls' => 'warning', 'icon' => 'clock'],
            RemittanceStatus::Pending->value    => ['cls' => 'info',    'icon' => 'hourglass-half'],
            RemittanceStatus::Failed->value     => ['cls' => 'danger',  'icon' => 'circle-exclamation'],
            RemittanceStatus::Refunded->value   => ['cls' => 'info',    'icon' => 'rotate-left'],
            RemittanceStatus::Canceled->value   => ['cls' => 'muted',   'icon' => 'xmark'],
        ];

        $tones = ['indigo','rose','amber','teal','violet','sky'];
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="single-form-card">
                @include('frontend.user.remittance.partials._page_header', [
                    'rmtPageTitle'    => __('Transfer History'),
                    'rmtPageSubtitle' => __('Track every remittance you have sent abroad.'),
                    'rmtPageIcon'     => 'fas fa-clock-rotate-left',
                    'rmtCurrentPage'  => 'index',
                ])
            </div>
        </div>
    </div>

    <div class="dk-rmt">

        {{-- Filter + Search --}}
        <form method="GET" class="dk-rmt__card mb-3" style="padding:14px;">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-7">
                    <div style="position:relative;">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--dk-rmt-muted);font-size:13px;pointer-events:none;"></i>
                        <input type="text" name="q" class="dk-rmt__input" style="padding-left:38px;"
                               value="{{ $search }}"
                               placeholder="{{ __('Search by reference, name or country...') }}">
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <div class="dk-rmt__chips" style="margin:0;">
                        @foreach ($filters as $key => $label)
                            <button type="submit" name="status" value="{{ $key }}"
                                    class="dk-rmt__chip {{ ($status === $key || ($status === '' && $key === '')) ? 'is-active' : '' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </form>

        {{-- Transfer list --}}
        @if ($transfers->isEmpty())
            @if ($search || $status)
                <x-user-not-found
                    :title="__('No transfers match your filter')"
                    :message="__('Try a different search term or clear the filter.')"
                    icon="fa-paper-plane"
                    :action-url="route('user.remittance.index')"
                    :action-label="__('Clear filters')"
                    action-icon="fa-rotate-left"
                />
            @else
                <x-user-not-found
                    :title="__('No transfers yet')"
                    :message="__('Your future international transfers will show up here.')"
                    icon="fa-paper-plane"
                    :action-url="route('user.remittance.create')"
                    :action-label="__('Send your first transfer')"
                    action-icon="fa-paper-plane"
                />
            @endif
        @else
            <div class="dk-rmt__history">
                @foreach ($transfers as $transfer)
                    @php
                        $pill = $statusToPill[$transfer->status->value] ?? ['cls' => 'muted', 'icon' => 'circle'];
                        $tone = $tones[($transfer->beneficiary?->id ?? 0) % count($tones)];
                        $initials = collect(explode(' ', $transfer->beneficiary->full_name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                    @endphp
                    <a class="dk-rmt__history-row" href="{{ route('user.remittance.show', $transfer->reference) }}">
                        <div style="position:relative;">
                            <span class="dk-rmt__avatar dk-rmt__avatar--{{ $tone }}">{{ $initials }}</span>
                            <span class="dk-rmt__recipient-flag">{{ getCountryFlagEmoji($transfer->beneficiary->country_code) }}</span>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="dk-rmt__history-name">{{ $transfer->beneficiary->full_name }}</div>
                            <div class="dk-rmt__history-amounts">
                                {{ __('Sent') }} <strong>{{ number_format($transfer->total_paid, 2) }} {{ $transfer->source_currency }}</strong>
                                · {{ __('Recipient') }} <strong>{{ number_format($transfer->receive_amount, 2) }} {{ $transfer->destination_currency }}</strong>
                            </div>
                        </div>
                        <div class="dk-rmt__history-meta">
                            <span class="dk-rmt__pill dk-rmt__pill--{{ $pill['cls'] }}">
                                <i class="fa-solid fa-{{ $pill['icon'] }}" style="font-size:9px;"></i>
                                {{ $transfer->status->label() }}
                            </span>
                            <span class="dk-rmt__history-time">
                                {{ \Carbon\Carbon::parse($transfer->created_at)->diffForHumans(null, true, true) }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($transfers->hasPages())
                <div class="mt-3 d-flex justify-content-center">
                    {{ $transfers->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
