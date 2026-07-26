@extends('frontend.layouts.user.index')
@section('title', __('Send Money Abroad'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/remittance.css?v=' . filemtime(public_path('frontend/css/remittance.css'))) }}">
@endpush

@section('content')
    @include('frontend.user.partials._feature_summary_statistics', ['trxType' => \App\Enums\TrxType::REMITTANCE_SEND])

    <div class="row">
        <div class="col-lg-12 col-xl-8">
            <div class="single-form-card">
                @include('frontend.user.remittance.partials._page_header', [
                    'rmtPageTitle'    => __('Send Money Abroad'),
                    'rmtPageSubtitle' => __('Live rates. No hidden fees. Track every step.'),
                    'rmtPageIcon'     => 'fas fa-globe',
                    'rmtCurrentPage'  => 'create',
                ])

                <div class="card-main">
                    @if ($corridors->isEmpty())
                        <x-user-not-found
                            :title="__('No corridors available yet')"
                            :message="__('International transfer corridors will appear here once an admin enables them. Check back soon.')"
                            icon="fa-globe"
                        />
                    @else
                        <div class="dk-rmt">
                            <form action="{{ route('user.remittance.quote') }}" method="POST" id="dk-rmt-form"
                                  onsubmit="disableSubmitButton(this, '{{ __('Getting Quote...') }}')">
                                @csrf

                                @if ($preselectedBeneficiary && ! $suggestedCorridor)
                                    <div class="dk-rmt__alert dk-rmt__alert--warning mb-3">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <div>{{ __('No active corridor matches :name (:currency). Please contact support.', [
                                            'name'     => $preselectedBeneficiary->full_name,
                                            'currency' => $preselectedBeneficiary->currency,
                                        ]) }}</div>
                                    </div>
                                @endif

                                {{-- 1. Country / Corridor --}}
                                <div class="mb-3">
                                    <label class="dk-rmt__label">
                                        <span>{{ __('Where are you sending money?') }}</span>
                                    </label>

                                    {{-- Quick chips --}}
                                    <div class="dk-rmt__chips">
                                        @foreach ($corridors->take(6) as $quick)
                                            <button type="button"
                                                    class="dk-rmt__chip"
                                                    data-quick-corridor="{{ $quick->id }}"
                                                    title="{{ $quick->destination_country }} · {{ $quick->destination_currency }}">
                                                {{-- Flag emoji renders as country letters (e.g. "BD") on Windows browsers
                                                     that don't ship a flag-emoji font. The currency code that follows
                                                     already conveys the country (BDT, NGN, INR…), so we don't repeat
                                                     the bare country code — that's where the duplicate "BD BD·BDT"
                                                     came from. The title attribute keeps the full label accessible. --}}
                                                <span class="dk-rmt__chip-flag" aria-hidden="true">{{ getCountryFlagEmoji($quick->destination_country) }}</span>
                                                <span class="dk-mono">{{ $quick->destination_currency }}</span>
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- Full corridor select --}}
                                    <select class="dk-rmt__select" name="corridor_id" id="corridor-select" required>
                                        <option value="" disabled @selected(! old('corridor_id') && ! $suggestedCorridor)>{{ __('Select corridor') }}</option>
                                        @foreach ($corridors as $corridor)
                                            <option value="{{ $corridor->id }}"
                                                    data-source-currency="{{ $corridor->source_currency }}"
                                                    data-dest-currency="{{ $corridor->destination_currency }}"
                                                    data-source-country="{{ $corridor->source_country }}"
                                                    data-dest-country="{{ $corridor->destination_country }}"
                                                    data-min="{{ $corridor->min_amount }}"
                                                    data-max="{{ $corridor->max_amount }}"
                                                    data-fixed-fee="{{ $corridor->fixed_fee }}"
                                                    data-percent-fee="{{ $corridor->percent_fee }}"
                                                    data-ttl="{{ $corridor->quote_ttl_minutes }}"
                                                    data-methods='@json($corridor->allowed_payout_methods)'
                                                    @selected(old('corridor_id') == $corridor->id || (! old('corridor_id') && $suggestedCorridor?->id === $corridor->id))>
                                                {{ getCountryFlagEmoji($corridor->destination_country) }}
                                                {{ $corridor->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('corridor_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>

                                {{-- 2. Amount field --}}
                                <div class="dk-rmt__amount mb-3">
                                    <label class="dk-rmt__label">
                                        <span>{{ __('You send') }}</span>
                                        <span class="dk-rmt__label-meta">
                                            <i class="fa-solid fa-bolt" style="color:var(--dk-rmt-warning);"></i>
                                            {{ __('Live rate') }}
                                        </span>
                                    </label>
                                    <div class="dk-rmt__amount-wrap">
                                        <input type="text" class="dk-rmt__input dk-rmt__input--lg" name="send_amount" id="send-amount"
                                               value="{{ old('send_amount') }}"
                                               placeholder="0.00"
                                               inputmode="decimal"
                                               autocomplete="off"
                                               data-1p-ignore="true"
                                               data-lpignore="true"
                                               oninput="this.value = validateDouble(this.value)" required>
                                        <span class="dk-rmt__amount-suffix">
                                            <span id="source-flag">🇺🇸</span>
                                            <span id="source-currency-label">USD</span>
                                        </span>
                                    </div>

                                    <div class="dk-rmt__row-divider"></div>

                                    <div class="dk-rmt__amount-preview">
                                        <div>
                                            <div class="dk-rmt__amount-preview-label">{{ __('Recipient gets') }}</div>
                                            <div class="dk-rmt__amount-preview-value" id="recipient-preview-row">
                                                <span id="recipient-amount">0.00</span>
                                                <small id="recipient-currency">BDT</small>
                                            </div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div class="dk-rmt__amount-rate-label">{{ __('Exchange rate') }}</div>
                                            <div class="dk-rmt__amount-rate dk-mono" id="exchange-rate">
                                                {{ __('Quote on next step') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="dk-rmt__amount-strip">
                                        <span class="dk-rmt__amount-strip-item">
                                            <i class="fa-solid fa-receipt"></i>
                                            {{ __('Fee') }} <strong id="fee-display">—</strong>
                                        </span>
                                        <span class="dk-rmt__amount-strip-dot"></span>
                                        <span class="dk-rmt__amount-strip-item">
                                            <i class="fa-solid fa-lock"></i>
                                            {{ __('Rate locks for') }} <strong id="ttl-display">15 min</strong>
                                        </span>
                                    </div>

                                    <div class="dk-rmt__amount-hint" id="amount-hint"></div>
                                    @error('send_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>

                                {{-- 3. Payout methods --}}
                                <div class="mb-3">
                                    <label class="dk-rmt__label">{{ __('How will they get paid?') }}</label>
                                    <div class="dk-rmt__methods" id="method-grid">
                                        <div class="dk-rmt__methods-hint">
                                            <i class="fa-regular fa-lightbulb" aria-hidden="true"></i>
                                            <span>{{ __('Pick a corridor above to load supported payout methods.') }}</span>
                                        </div>
                                    </div>
                                    <input type="hidden" name="payout_method" id="payout-method-input" value="{{ old('payout_method') }}">
                                    @error('payout_method')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>

                                {{-- 4. Recipient picker --}}
                                <div class="mb-3">
                                    <label class="dk-rmt__label">
                                        <span>{{ __('Send to') }}</span>
                                        <a href="{{ route('user.beneficiaries.index') }}" class="dk-rmt__label-link">
                                            {{ __('Manage recipients') }} <i class="fa-solid fa-arrow-right-long" style="font-size:9px;"></i>
                                        </a>
                                    </label>

                                    @if ($beneficiaries->isEmpty())
                                        {{-- Premium empty-state card — clear next action, fills the row --}}
                                        <a class="dk-rmt__recipients-empty" href="{{ route('user.beneficiaries.create') }}">
                                            <span class="dk-rmt__recipients-empty-icon">
                                                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                                            </span>
                                            <span class="dk-rmt__recipients-empty-body">
                                                <span class="dk-rmt__recipients-empty-title">{{ __('Add your first recipient') }}</span>
                                                <span class="dk-rmt__recipients-empty-sub">{{ __('Save a bank account or mobile wallet to send abroad in seconds.') }}</span>
                                            </span>
                                            <span class="dk-rmt__recipients-empty-cta">
                                                {{ __('Add') }}
                                                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                            </span>
                                        </a>
                                    @else
                                        <div class="dk-rmt__recipients">
                                            @foreach ($beneficiaries as $i => $beneficiary)
                                                @php
                                                    $tones = ['indigo','rose','amber','teal','violet','sky'];
                                                    $tone  = $tones[$i % count($tones)];
                                                    $initials = collect(explode(' ', $beneficiary->full_name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
                                                @endphp
                                                <label class="dk-rmt__recipient {{ ($preselectedBeneficiary?->id === $beneficiary->id || old('beneficiary_id') == $beneficiary->id) ? 'is-active' : '' }}"
                                                       data-recipient-id="{{ $beneficiary->id }}"
                                                       data-currency="{{ $beneficiary->currency }}">
                                                    <input type="radio" name="beneficiary_id" value="{{ $beneficiary->id }}"
                                                           class="dk-rmt__recipient-input"
                                                           @checked($preselectedBeneficiary?->id === $beneficiary->id || old('beneficiary_id') == $beneficiary->id)>
                                                    <div class="dk-rmt__recipient-photo">
                                                        <span class="dk-rmt__avatar dk-rmt__avatar--lg dk-rmt__avatar--{{ $tone }}">{{ $initials }}</span>
                                                        <span class="dk-rmt__recipient-flag">{{ getCountryFlagEmoji($beneficiary->country_code) }}</span>
                                                    </div>
                                                    <div class="dk-rmt__recipient-name">{{ explode(' ', $beneficiary->full_name)[0] }}</div>
                                                </label>
                                            @endforeach
                                            <a class="dk-rmt__recipient dk-rmt__recipient--new" href="{{ route('user.beneficiaries.create') }}">
                                                <span class="dk-rmt__avatar dk-rmt__avatar--lg"><i class="fa-solid fa-plus"></i></span>
                                                <div class="dk-rmt__recipient-name">{{ __('New') }}</div>
                                            </a>
                                        </div>
                                    @endif

                                    @error('beneficiary_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>

                                {{-- Footer --}}
                                <div class="dk-rmt__cta-row">
                                    <div class="dk-rmt__cta-safeguard">
                                        <i class="fa-solid fa-shield-halved" style="color:var(--dk-rmt-success);"></i>
                                        {{ __('Protected by Digikash Safeguard') }}
                                    </div>
                                    <button type="submit" class="dk-rmt__btn dk-rmt__btn--primary">
                                        {{ __('Get Live Quote') }}
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right rail --}}
        <div class="col-lg-12 col-xl-4">
            <div class="dk-rmt">
                @if ($primaryWallet)
                    <div class="single-form-card mb-3">
                        <div class="card-main">
                            <div class="dk-rmt__wallet-head">
                                <span class="dk-rmt__iconbox"><i class="fa-solid fa-wallet"></i></span>
                                <div>
                                    <div class="dk-rmt__wallet-label">{{ __('Funding wallet') }}</div>
                                    <div class="dk-rmt__wallet-name">{{ $primaryWallet->currency->code }} {{ __('Wallet') }}</div>
                                </div>
                            </div>
                            <div class="dk-rmt__wallet-amount">
                                {{ $primaryWallet->currency->symbol ?? '' }}{{ number_format((float) $primaryWallet->balance, 2) }}
                            </div>
                            <div class="dk-rmt__wallet-sub">{{ __('Available balance') }}</div>
                            <a class="dk-rmt__btn dk-rmt__btn--secondary dk-rmt__btn--full mt-3" href="{{ route('user.deposit.create') }}">
                                <i class="fa-solid fa-plus"></i> {{ __('Top up') }}
                            </a>
                        </div>
                    </div>
                @endif

                <div class="single-form-card">
                    <div class="card-main">
                        <div class="dk-rmt__rail-title">
                            <i class="fa-solid fa-sparkles" style="color:var(--dk-rmt-primary);"></i>
                            {{ __('Why Digikash') }}
                        </div>
                        <div class="dk-rmt__props">
                            <div class="dk-rmt__prop">
                                <span class="dk-rmt__iconbox dk-rmt__iconbox--sm dk-rmt__iconbox--success"><i class="fa-solid fa-bolt"></i></span>
                                <div>
                                    <div class="dk-rmt__prop-title">{{ __('Money arrives in minutes') }}</div>
                                    <div class="dk-rmt__prop-sub">{{ __('Most corridors < 30 min') }}</div>
                                </div>
                            </div>
                            <div class="dk-rmt__prop">
                                <span class="dk-rmt__iconbox dk-rmt__iconbox--sm dk-rmt__iconbox--success"><i class="fa-solid fa-tag"></i></span>
                                <div>
                                    <div class="dk-rmt__prop-title">{{ __('Always show real fees') }}</div>
                                    <div class="dk-rmt__prop-sub">{{ __('No hidden FX margin') }}</div>
                                </div>
                            </div>
                            <div class="dk-rmt__prop">
                                <span class="dk-rmt__iconbox dk-rmt__iconbox--sm dk-rmt__iconbox--success"><i class="fa-solid fa-lock"></i></span>
                                <div>
                                    <div class="dk-rmt__prop-title">{{ __('Bank-grade encryption') }}</div>
                                    <div class="dk-rmt__prop-sub">{{ __('KYC verified · audited') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form           = document.getElementById('dk-rmt-form');
            if (! form) { return; }
            const corridorSelect = document.getElementById('corridor-select');
            const amountInput    = document.getElementById('send-amount');
            const sourceLabel    = document.getElementById('source-currency-label');
            const sourceFlag     = document.getElementById('source-flag');
            const recipientAmt   = document.getElementById('recipient-amount');
            const recipientCur   = document.getElementById('recipient-currency');
            const rateLabel      = document.getElementById('exchange-rate');
            const feeDisplay     = document.getElementById('fee-display');
            const ttlDisplay     = document.getElementById('ttl-display');
            const amountHint     = document.getElementById('amount-hint');
            const methodGrid     = document.getElementById('method-grid');
            const payoutInput    = document.getElementById('payout-method-input');

            const payoutLabels = @json(collect(\App\Enums\PayoutMethod::cases())->mapWithKeys(fn ($m) => [$m->value => ['label' => $m->label(), 'icon' => $m->icon()]]));
            const payoutSubs = {
                'bank_account':  '{{ __('Direct bank transfer · 1–2 days') }}',
                'mobile_wallet': '{{ __('bKash, M-Pesa, GCash · minutes') }}',
                'cash_pickup':   '{{ __('Pickup partner location') }}',
                'debit_card':    '{{ __('Push-to-card · instant') }}',
                'wallet_credit': '{{ __('Internal wallet credit') }}',
            };

            function flagOf(code) {
                if (! code) return '';
                code = code.toUpperCase();
                if (! /^[A-Z]{2}$/.test(code)) return '';
                return String.fromCodePoint(0x1F1E6 + code.charCodeAt(0) - 65)
                     + String.fromCodePoint(0x1F1E6 + code.charCodeAt(1) - 65);
            }

            function formatMoney(n, dp) {
                if (! isFinite(n)) return '0.00';
                return Number(n).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });
            }

            function renderMethods(option, selectedValue) {
                const methods = JSON.parse(option.dataset.methods || '[]');
                if (! methods.length) {
                    methodGrid.innerHTML = '<div class="dk-rmt__methods-hint dk-rmt__methods-hint--warning"><i class="fa-solid fa-circle-exclamation"></i><span>{{ __('No payout methods available on this corridor.') }}</span></div>';
                    return;
                }
                methodGrid.innerHTML = '';
                let active = selectedValue;
                if (! active || methods.indexOf(active) < 0) {
                    active = methods[0];
                }
                methods.forEach(function (m) {
                    const info = payoutLabels[m] || { label: m, icon: 'fa-solid fa-receipt' };
                    const sub  = payoutSubs[m] || '';
                    const isOn = (active === m);
                    const label = document.createElement('label');
                    label.className = 'dk-rmt__method' + (isOn ? ' is-on' : '');
                    label.innerHTML = `
                        <input type="radio" class="dk-rmt__method-radio" value="${m}" ${isOn ? 'checked' : ''}>
                        <span class="dk-rmt__iconbox"><i class="${info.icon}"></i></span>
                        <span class="dk-rmt__method-body">
                            <span class="dk-rmt__method-name">${info.label}</span>
                            <span class="dk-rmt__method-sub">${sub}</span>
                        </span>
                        <span class="dk-rmt__method-dot"></span>
                    `;
                    label.addEventListener('click', function () { selectMethod(m); });
                    methodGrid.appendChild(label);
                });
                payoutInput.value = active;
            }

            function selectMethod(value) {
                payoutInput.value = value;
                methodGrid.querySelectorAll('.dk-rmt__method').forEach(function (el) {
                    const input = el.querySelector('input');
                    el.classList.toggle('is-on', input && input.value === value);
                });
            }

            function syncCorridor() {
                const option = corridorSelect.options[corridorSelect.selectedIndex];
                if (! option || ! option.value) { return; }

                sourceLabel.textContent  = option.dataset.sourceCurrency;
                sourceFlag.textContent   = flagOf(option.dataset.sourceCountry);
                recipientCur.textContent = option.dataset.destCurrency;
                ttlDisplay.textContent   = (option.dataset.ttl || 15) + ' min';
                amountHint.textContent   = '{{ __('Min :min — Max :max :cur', ['min' => '__MIN__', 'max' => '__MAX__', 'cur' => '__CUR__']) }}'
                    .replace('__MIN__', Number(option.dataset.min).toLocaleString())
                    .replace('__MAX__', Number(option.dataset.max).toLocaleString())
                    .replace('__CUR__', option.dataset.sourceCurrency);

                renderMethods(option, payoutInput.value);
                updateAmountPreview();

                document.querySelectorAll('[data-quick-corridor]').forEach(function (chip) {
                    chip.classList.toggle('is-active', chip.dataset.quickCorridor === option.value);
                });
            }

            function updateAmountPreview() {
                const option = corridorSelect.options[corridorSelect.selectedIndex];
                if (! option || ! option.value) { return; }

                const amount = parseFloat(amountInput.value) || 0;
                const fixed  = parseFloat(option.dataset.fixedFee) || 0;
                const pct    = parseFloat(option.dataset.percentFee) || 0;
                const fee    = fixed + (amount * pct / 100);

                feeDisplay.textContent = formatMoney(fee, 2) + ' ' + option.dataset.sourceCurrency;
                rateLabel.textContent  = '{{ __('Quote on next step') }}';
                recipientAmt.textContent = amount > 0 ? '≈ ' + formatMoney(amount, 0) : '0.00';
            }

            corridorSelect.addEventListener('change', syncCorridor);
            amountInput.addEventListener('input', updateAmountPreview);

            document.querySelectorAll('[data-quick-corridor]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    corridorSelect.value = chip.dataset.quickCorridor;
                    syncCorridor();
                });
            });

            document.querySelectorAll('.dk-rmt__recipient[data-recipient-id]').forEach(function (card) {
                card.addEventListener('click', function (e) {
                    if (e.target.tagName === 'INPUT') { return; }
                    document.querySelectorAll('.dk-rmt__recipient[data-recipient-id]').forEach(function (c) { c.classList.remove('is-active'); });
                    card.classList.add('is-active');
                    const input = card.querySelector('input[type="radio"]');
                    if (input) { input.checked = true; }
                });
            });

            if (corridorSelect.value) { syncCorridor(); }
        })();
    </script>
@endpush
