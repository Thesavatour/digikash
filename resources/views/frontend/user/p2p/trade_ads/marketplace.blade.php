@extends('frontend.layouts.user.index')
@section('title', __('P2P Marketplace'))
@section('content')
{{-- Marketplace Screen: listing filters, trade ads, CTA cards, and trade order modal --}}
<div class="single-form-card p2p-ui">
    <x-user-feature-header
        :title="__('P2P Marketplace')"
        :subtitle="__('Browse live trade ads and launch your own listing from the same workspace.')"
        icon="fas fa-store"
    >
        @if($subscriptionGates['can_create_ad'])
            <a href="{{ route('user.p2p.offers.create') }}" class="btn btn-light-success btn-sm">
                <i class="fas fa-plus"></i> <span class="p2p-new-listing-text">@lang('Create Trade Ad')</span>
            </a>
        @else
            <button type="button" class="btn btn-light-success btn-sm js-p2p-upgrade-prompt"
                    data-feature-label="{{ __('Create P2P Trade Ad') }}"
                    data-feature-message="{{ __('Creating P2P trade ads is a paid feature. Upgrade to Pro or Business to publish your own listings.') }}">
                <i class="fas fa-lock"></i> <span class="p2p-new-listing-text">@lang('Create Trade Ad')</span>
            </button>
        @endif
    </x-user-feature-header>
    <div class="card-main p2p-card-main p2p-card-main--marketplace">

    @php
        $side = request('side');
        $sideEnum = \App\Enums\P2P\OrderSide::tryFrom($side);
        $activeBuyTab  = $sideEnum === \App\Enums\P2P\OrderSide::SELL || $sideEnum === null;
        $activeSellTab = $sideEnum === \App\Enums\P2P\OrderSide::BUY;
        $buySideValue  = \App\Enums\P2P\OrderSide::SELL->value;
        $sellSideValue = \App\Enums\P2P\OrderSide::BUY->value;
        $sideValue     = $activeBuyTab ? $buySideValue : $sellSideValue;

        $coinMeta = [
            'USDT' => ['sym' => '₮', 'bg' => '#26A17B'],
            'USDC' => ['sym' => '$', 'bg' => '#2775CA'],
            'BTC'  => ['sym' => '₿', 'bg' => '#F7931A'],
            'ETH'  => ['sym' => 'Ξ', 'bg' => '#627EEA'],
            'BNB'  => ['sym' => 'B', 'bg' => '#F0B90B'],
            'TRX'  => ['sym' => 'T', 'bg' => '#EF0027'],
        ];

        $coinPriority = ['USDT' => 1, 'USDC' => 2, 'BTC' => 3, 'ETH' => 4, 'BNB' => 5, 'TRX' => 6];
        $assetOptions = $currencyOptions->sortBy(fn ($code) => $coinPriority[$code] ?? 99)->values();

        $preservedFilters = array_filter([
            'amount_min'        => request('amount_min'),
            'amount_max'        => request('amount_max'),
            'currency'          => request('currency'),
            'fiat'              => request('fiat'),
            'payment_method_id' => request('payment_method_id'),
            'sort'              => request('sort'),
        ], fn ($value) => $value !== null && $value !== '');

        $totalOffers   = $offers->total();
        $selectedAsset = request('currency') ?: __('All assets');
        $countText     = $totalOffers.' '.($totalOffers === 1 ? __('offer') : __('offers')).' · '.$selectedAsset.' · '.($activeBuyTab ? __('Buying') : __('Selling'));
    @endphp

    {{-- Top bar: Buy/Sell side toggle + crypto asset pills --}}
    <div class="p2p-mkt-topbar">
        <div class="p2p-mkt-side-toggle" role="tablist">
            <a class="p2p-mkt-side-btn p2p-mkt-side-btn--buy {{ $activeBuyTab ? 'is-active' : '' }}"
               role="tab" aria-selected="{{ $activeBuyTab ? 'true' : 'false' }}"
               href="{{ route('user.p2p.offers.index', array_merge(['side' => $buySideValue], $preservedFilters)) }}">
                <i class="fas fa-arrow-up"></i> @lang('Buy Crypto')
            </a>
            <a class="p2p-mkt-side-btn p2p-mkt-side-btn--sell {{ $activeSellTab ? 'is-active' : '' }}"
               role="tab" aria-selected="{{ $activeSellTab ? 'true' : 'false' }}"
               href="{{ route('user.p2p.offers.index', array_merge(['side' => $sellSideValue], $preservedFilters)) }}">
                <i class="fas fa-arrow-down"></i> @lang('Sell Crypto')
            </a>
        </div>

        @if($assetOptions->isNotEmpty())
            <div class="p2p-mkt-coins" role="group" aria-label="@lang('Crypto asset')">
                @foreach($assetOptions as $assetCode)
                    @php
                        $meta = $coinMeta[$assetCode] ?? ['sym' => strtoupper(substr($assetCode, 0, 1)), 'bg' => '#64748B'];
                        $coinActive = request('currency') === $assetCode;
                        $coinFilters = \Illuminate\Support\Arr::except($preservedFilters, 'currency');
                        $coinUrl = $coinActive
                            ? route('user.p2p.offers.index', array_merge(['side' => $sideValue], $coinFilters))
                            : route('user.p2p.offers.index', array_merge(['side' => $sideValue, 'currency' => $assetCode], $coinFilters));
                    @endphp
                    <a href="{{ $coinUrl }}" class="p2p-mkt-coin {{ $coinActive ? 'is-active' : '' }}" aria-pressed="{{ $coinActive ? 'true' : 'false' }}">
                        <span class="p2p-mkt-coin__sym" style="background: {{ $meta['bg'] }};">{{ $meta['sym'] }}</span>
                        {{ $assetCode }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Resume Banner --}}
    <div id="p2pResumeBanner" class="p2p-resume-banner d-none" role="status" aria-live="polite">
        <div class="p2p-resume-left">
            <span class="p2p-resume-icon-wrap">
                <i class="fas fa-clock-rotate-left p2p-resume-icon"></i>
            </span>
            <div class="p2p-resume-content">
                <div class="p2p-resume-title-row">
                    <span class="p2p-resume-title">@lang('Resume last trade')</span>
                    <span id="p2pResumeStatus" class="p2p-resume-status d-none"></span>
                </div>
                <div class="p2p-resume-order-row">
                    <strong id="p2pResumeOrderText">@lang('Order') #</strong>
                    <span id="p2pResumeUpdated" class="p2p-resume-updated d-none"></span>
                </div>
                <div id="p2pResumeMeta" class="p2p-resume-meta"></div>
            </div>
        </div>
        <a id="p2pResumeLink" href="#" class="btn btn-light-primary btn-sm p2p-btn-xs p2p-resume-link">
            <i class="fas fa-sync-alt me-1"></i> @lang('Resume Trade')
            <i class="fas fa-chevron-right ms-1"></i>
        </a>
    </div>

    {{-- Filter bar: fiat currency, payment method, amount range, search --}}
    <form method="GET" action="{{ route('user.p2p.offers.index') }}" class="p2p-mkt-filterbar">
        <input type="hidden" name="side" value="{{ $sideValue }}">
        <input type="hidden" name="currency" value="{{ request('currency') }}">
        @if(request('sort'))
            <input type="hidden" name="sort" value="{{ request('sort') }}">
        @endif

        {{-- Dashboard selects: dashboard-main.js enhances these into the global premium dropdown UI. --}}
        <div class="p2p-mkt-fcol">
            <label class="visually-hidden" for="p2pFilterFiat">@lang('Currency')</label>
            <select id="p2pFilterFiat" name="fiat" class="form-select form-select-sm p2p-mkt-select" data-dk-premium-icon="fa-coins">
                <option value="">@lang('All currencies')</option>
                @foreach($fiatOptions as $code)
                    <option value="{{ $code }}" @selected(request('fiat')===$code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>

        <div class="p2p-mkt-fcol">
            <label class="visually-hidden" for="p2pFilterPayment">@lang('Payment Method')</label>
            <select id="p2pFilterPayment" name="payment_method_id" class="form-select form-select-sm p2p-mkt-select" data-dk-premium-icon="fa-credit-card">
                <option value="">@lang('All payment methods')</option>
                @foreach($methods as $m)
                    <option value="{{ $m->id }}" @selected((string)request('payment_method_id') === (string)$m->id)>{{ $m->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="p2p-mkt-fcol p2p-mkt-fcol--range">
            <label class="visually-hidden" for="p2pFilterMin">@lang('Amount Range')</label>
            <input type="text" id="p2pFilterMin" inputmode="decimal" name="amount_min"
                   value="{{ request('amount_min') }}" class="p2p-mkt-input"
                   placeholder="@lang('Min amount')" oninput="this.value = validateDouble(this.value)">
            <span class="p2p-mkt-range-sep">–</span>
            <input type="text" inputmode="decimal" name="amount_max"
                   value="{{ request('amount_max') }}" class="p2p-mkt-input"
                   placeholder="@lang('Max')" oninput="this.value = validateDouble(this.value)">
        </div>

        <button type="submit" class="p2p-mkt-search">
            <i class="fas fa-search"></i> @lang('Search') <i class="fas fa-arrow-right"></i>
        </button>
    </form>

    {{-- Toolbar: result count + sort + refresh --}}
    <div class="p2p-mkt-toolbar">
        <div class="p2p-mkt-count">
            <span class="p2p-mkt-count__dot" aria-hidden="true"></span>
            <span>{{ $countText }}</span>
        </div>
        <div class="p2p-mkt-toolbar__actions">
            <form method="GET" action="{{ route('user.p2p.offers.index') }}" class="p2p-mkt-sort">
                <input type="hidden" name="side" value="{{ $sideValue }}">
                @foreach(\Illuminate\Support\Arr::except($preservedFilters, 'sort') as $filterKey => $filterValue)
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endforeach
                <select name="sort" class="p2p-mkt-sort__select" data-no-premium-select onchange="this.form.submit()" aria-label="@lang('Sort offers')">
                    <option value="" @selected(! in_array(request('sort'), ['completion', 'trades'], true))>@lang('Sort: Best price')</option>
                    <option value="completion" @selected(request('sort')==='completion')>@lang('Sort: Completion rate')</option>
                    <option value="trades" @selected(request('sort')==='trades')>@lang('Sort: Most trades')</option>
                </select>
                <i class="fas fa-chevron-down p2p-mkt-select__chevron" aria-hidden="true"></i>
            </form>
            <a href="{{ route('user.p2p.offers.index', array_merge(['side' => $sideValue], $preservedFilters)) }}"
               class="p2p-mkt-refresh" title="@lang('Refresh offers')" aria-label="@lang('Refresh offers')">
                <i class="fas fa-rotate-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    {{-- Column labels (desktop) --}}
    <div class="p2p-mkt-listhead" aria-hidden="true">
        <span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--advertiser">@lang('Advertiser')</span>
        <span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--price">@lang('Price')</span>
        <span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--limit">@lang('Available / Limit')</span>
        <span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--payment">@lang('Payment')</span>
        <span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--trade">@lang('Trade')</span>
    </div>

    <div class="p2p-offers-panel p2p-offers-panel--marketplace">
        <div id="p2pOffersList"
             class="p2p-offers-panel__body"
             data-current-page="{{ $offers->currentPage() }}"
             data-last-page="{{ $offers->lastPage() }}"
             data-next-page="{{ $offers->hasMorePages() ? $offers->currentPage() + 1 : 0 }}">
            <div id="p2pOffersItems">
                @include('frontend.user.p2p.trade_ads.partials._trade_ad_rows', ['offers' => $offers, 'sellerStats' => $sellerStats, 'marketplaceRow' => true])
            </div>
            <div id="p2pOffersLoader" class="p2p-offers-panel__status d-none">
                <i class="fas fa-spinner fa-spin me-1"></i> @lang('Loading more trade ads...')
            </div>
            <div id="p2pOffersEnd" class="p2p-offers-panel__status d-none">
                @lang('You have reached the end of the available trade ads.')
            </div>
        </div>
    </div>

    <div id="p2pOffersPagination" class="mt-3">
        {{ $offers->links() }}
    </div>

    </div>
</div>

@include('frontend.user.p2p.trade_ads.partials._marketplace_order_modal')

<x-upgrade-prompt-modal id="p2pUpgradeModal" />

{{-- Marketplace Script: resume state, modal population, payment selection, and total calculations --}}
@push('scripts')
    @include('frontend.user.p2p.trade_ads.partials._marketplace_scripts')

    <script>
    "use strict";
    (function () {
        const canTrade    = @json((bool) $subscriptionGates['can_trade']);
        const upgradeEl   = document.getElementById('p2pUpgradeModal');
        if (!upgradeEl || typeof bootstrap === 'undefined') { return; }
        const upgradeModal = new bootstrap.Modal(upgradeEl);

        function showUpgradePrompt(featureLabel, message) {
            const labelEl   = upgradeEl.querySelector('[data-upgrade-feature-label]');
            const messageEl = upgradeEl.querySelector('[data-upgrade-message]');
            if (labelEl && featureLabel)   { labelEl.textContent   = featureLabel; }
            if (messageEl && message)      { messageEl.textContent = message; }
            upgradeModal.show();
        }

        // Create Ad button (already gated server-side, this also intercepts client-side clicks)
        document.querySelectorAll('.js-p2p-upgrade-prompt').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                showUpgradePrompt(btn.dataset.featureLabel || '', btn.dataset.featureMessage || '');
            });
        });

        // Buy / Sell buttons on offer rows — intercept before the trade modal opens.
        if (!canTrade) {
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-bs-target="#p2pOrderModal"]');
                if (!btn) { return; }
                e.preventDefault();
                e.stopPropagation();
                const action = (btn.dataset.action || '').toUpperCase();
                showUpgradePrompt(
                    action === 'BUY'
                        ? @json(__('Buy on P2P Marketplace'))
                        : @json(__('Sell on P2P Marketplace')),
                    @json(__('P2P trading (Buy/Sell) is a paid feature. Free users can browse all offers - upgrade to Pro or Business to start trading.'))
                );
            }, true);
        }
    }());
    </script>
@endpush
@endsection
