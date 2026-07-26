@php
    $selectedMethods = collect((array) old('allowed_payout_methods', $corridor->allowed_payout_methods ?? []))
        ->map(fn ($v) => (string) $v)
        ->all();
    $currentGateway = (string) old('payout_gateway', $corridor->payout_gateway ?? '');
@endphp

<form action="{{ $action }}" method="POST" class="rmt-form" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Section 1: Identity --}}
    <div class="rmt-form__card">
        <div class="rmt-form__section-head">
            <span class="rmt-form__section-num">1</span>
            <div>
                <h6 class="rmt-form__section-title">{{ __('Identity') }}</h6>
                <p class="rmt-form__section-hint">{{ __('Where does the money go from and to? Pick the country and currency on each side.') }}</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <label class="rmt-form__label">{{ __('Display Name') }}</label>
                <input type="text" class="form-control rmt-form__input" name="name"
                       value="{{ old('name', $corridor->name) }}"
                       placeholder="{{ __('e.g. USD → BDT (Bangladesh)') }}"
                       autocomplete="off"
                       data-1p-ignore="true"
                       data-lpignore="true"
                       data-bwignore="true"
                       data-form-type="other"
                       required>
                <small class="rmt-form__help">{{ __('Shown to users in the corridor picker.') }}</small>
                @error('name')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <div class="rmt-form__lane">
                    <span class="rmt-form__lane-label">
                        <i class="fa-solid fa-paper-plane"></i>
                        {{ __('Source (where the sender pays from)') }}
                    </span>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="rmt-form__label">{{ __('Country') }}</label>
                            <select class="form-select rmt-form__input" name="source_country" required>
                                <option value="">{{ __('Select country') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country['code'] }}"
                                            @selected(strtoupper(old('source_country', $corridor->source_country ?? '')) === $country['code'])>
                                        {{ getCountryFlagEmoji($country['code']) }} {{ $country['name'] }} ({{ $country['code'] }})
                                    </option>
                                @endforeach
                            </select>
                            @error('source_country')<div class="rmt-form__error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-5">
                            <label class="rmt-form__label">{{ __('Currency') }}</label>
                            <select class="form-select rmt-form__input rmt-form__mono" name="source_currency" required>
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->code }}"
                                            @selected(strtoupper(old('source_currency', $corridor->source_currency ?? '')) === $currency->code)>
                                        {{ $currency->code }} {{ $currency->symbol ? '· '.$currency->symbol : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_currency')<div class="rmt-form__error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="rmt-form__lane rmt-form__lane--dest">
                    <span class="rmt-form__lane-label">
                        <i class="fa-solid fa-bullseye"></i>
                        {{ __('Destination (where the recipient gets paid)') }}
                    </span>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="rmt-form__label">{{ __('Country') }}</label>
                            <select class="form-select rmt-form__input" name="destination_country" required>
                                <option value="">{{ __('Select country') }}</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country['code'] }}"
                                            @selected(strtoupper(old('destination_country', $corridor->destination_country ?? '')) === $country['code'])>
                                        {{ getCountryFlagEmoji($country['code']) }} {{ $country['name'] }} ({{ $country['code'] }})
                                    </option>
                                @endforeach
                            </select>
                            @error('destination_country')<div class="rmt-form__error">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-5">
                            <label class="rmt-form__label">{{ __('Currency') }}</label>
                            <select class="form-select rmt-form__input rmt-form__mono" name="destination_currency" required>
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->code }}"
                                            @selected(strtoupper(old('destination_currency', $corridor->destination_currency ?? '')) === $currency->code)>
                                        {{ $currency->code }} {{ $currency->symbol ? '· '.$currency->symbol : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('destination_currency')<div class="rmt-form__error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 2: Payout Methods --}}
    <div class="rmt-form__card">
        <div class="rmt-form__section-head">
            <span class="rmt-form__section-num">2</span>
            <div>
                <h6 class="rmt-form__section-title">{{ __('Payout Methods') }}</h6>
                <p class="rmt-form__section-hint">{{ __('How can the recipient receive the money on this corridor? Pick at least one.') }}</p>
            </div>
        </div>

        <div class="rmt-form__method-grid">
            @foreach ($payoutMethods as $method)
                <label class="rmt-form__method @if (in_array($method->value, $selectedMethods, true)) rmt-form__method--on @endif">
                    <input class="rmt-form__method-input" type="checkbox"
                           name="allowed_payout_methods[]"
                           value="{{ $method->value }}"
                           @checked(in_array($method->value, $selectedMethods, true))>
                    <span class="rmt-form__method-icon">
                        <i class="{{ $method->icon() }}"></i>
                    </span>
                    <span class="rmt-form__method-body">
                        <span class="rmt-form__method-name">{{ $method->label() }}</span>
                        <span class="rmt-form__method-hint">
                            @switch($method)
                                @case(\App\Enums\PayoutMethod::BankAccount)    {{ __('Direct bank transfer to recipient') }} @break
                                @case(\App\Enums\PayoutMethod::MobileWallet)   {{ __('bKash, M-Pesa, GCash, etc.') }} @break
                                @case(\App\Enums\PayoutMethod::CashPickup)     {{ __('Pickup partner location') }} @break
                                @case(\App\Enums\PayoutMethod::DebitCard)      {{ __('Push-to-card delivery') }} @break
                                @case(\App\Enums\PayoutMethod::WalletCredit)   {{ __('Internal wallet credit') }} @break
                            @endswitch
                        </span>
                    </span>
                    <span class="rmt-form__method-check" aria-hidden="true">
                        <i class="fa-solid fa-check"></i>
                    </span>
                </label>
            @endforeach
        </div>
        @error('allowed_payout_methods')<div class="rmt-form__error mt-2">{{ $message }}</div>@enderror
    </div>

    {{-- Section 3: Routing & Compliance --}}
    <div class="rmt-form__card">
        <div class="rmt-form__section-head">
            <span class="rmt-form__section-num">3</span>
            <div>
                <h6 class="rmt-form__section-title">{{ __('Routing & Compliance') }}</h6>
                <p class="rmt-form__section-hint">{{ __('Which payout partner moves the money, and what KYC level is required?') }}</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-7">
                <label class="rmt-form__label">{{ __('Payout Gateway Driver') }}</label>
                <select class="form-select rmt-form__input" name="payout_gateway" required>
                    <option value="">{{ __('Select driver') }}</option>
                    @foreach ($gateways as $code => $label)
                        <option value="{{ $code }}" @selected($currentGateway === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="rmt-form__help">{{ __('Driver that actually delivers funds. Use "Manual" for admin-approved corridors.') }}</small>
                @error('payout_gateway')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-5">
                <label class="rmt-form__label">{{ __('Required KYC Tier') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" class="form-control rmt-form__input" name="required_kyc_tier" min="0" max="5"
                           value="{{ old('required_kyc_tier', $corridor->required_kyc_tier ?? 1) }}">
                    <span class="rmt-form__input-suffix">{{ __('Tier 0–5') }}</span>
                </div>
                <small class="rmt-form__help">{{ __('0 = no verification needed. Higher = stricter limits + AML checks.') }}</small>
                @error('required_kyc_tier')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Section 4: Pricing --}}
    <div class="rmt-form__card">
        <div class="rmt-form__section-head">
            <span class="rmt-form__section-num">4</span>
            <div>
                <h6 class="rmt-form__section-title">{{ __('Pricing') }}</h6>
                <p class="rmt-form__section-hint">{{ __('FX margin and transfer fees you charge per send on this corridor.') }}</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="rmt-form__label">{{ __('FX Spread') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" step="0.0001" class="form-control rmt-form__input rmt-form__mono" name="fx_spread_percent"
                           value="{{ old('fx_spread_percent', $corridor->fx_spread_percent ?? 0) }}">
                    <span class="rmt-form__input-suffix">%</span>
                </div>
                <small class="rmt-form__help">{{ __('Margin added on top of mid-market rate.') }}</small>
                @error('fx_spread_percent')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="rmt-form__label">{{ __('Fixed Fee') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" step="0.01" class="form-control rmt-form__input rmt-form__mono" name="fixed_fee"
                           value="{{ old('fixed_fee', $corridor->fixed_fee ?? 0) }}">
                    <span class="rmt-form__input-suffix" data-fx-suffix>{{ strtoupper((string) old('source_currency', $corridor->source_currency ?? 'USD')) }}</span>
                </div>
                <small class="rmt-form__help">{{ __('Flat fee per transfer, in source currency.') }}</small>
                @error('fixed_fee')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="rmt-form__label">{{ __('Percent Fee') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" step="0.0001" class="form-control rmt-form__input rmt-form__mono" name="percent_fee"
                           value="{{ old('percent_fee', $corridor->percent_fee ?? 0) }}">
                    <span class="rmt-form__input-suffix">%</span>
                </div>
                <small class="rmt-form__help">{{ __('Charged on send amount, on top of fixed fee.') }}</small>
                @error('percent_fee')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Section 5: Limits --}}
    <div class="rmt-form__card">
        <div class="rmt-form__section-head">
            <span class="rmt-form__section-num">5</span>
            <div>
                <h6 class="rmt-form__section-title">{{ __('Limits & Quote Lifetime') }}</h6>
                <p class="rmt-form__section-hint">{{ __('Per-transaction send limits in source currency, and how long a generated quote stays valid.') }}</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="rmt-form__label">{{ __('Minimum Amount') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" step="0.01" class="form-control rmt-form__input rmt-form__mono" name="min_amount"
                           value="{{ old('min_amount', $corridor->min_amount ?? 1) }}" required>
                    <span class="rmt-form__input-suffix" data-fx-suffix>{{ strtoupper((string) old('source_currency', $corridor->source_currency ?? 'USD')) }}</span>
                </div>
                @error('min_amount')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="rmt-form__label">{{ __('Maximum Amount') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" step="0.01" class="form-control rmt-form__input rmt-form__mono" name="max_amount"
                           value="{{ old('max_amount', $corridor->max_amount ?? 10000) }}" required>
                    <span class="rmt-form__input-suffix" data-fx-suffix>{{ strtoupper((string) old('source_currency', $corridor->source_currency ?? 'USD')) }}</span>
                </div>
                @error('max_amount')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label class="rmt-form__label">{{ __('Quote TTL') }}</label>
                <div class="rmt-form__input-wrap">
                    <input type="number" class="form-control rmt-form__input rmt-form__mono" name="quote_ttl_minutes" min="1" max="1440"
                           value="{{ old('quote_ttl_minutes', $corridor->quote_ttl_minutes ?? 15) }}">
                    <span class="rmt-form__input-suffix">{{ __('min') }}</span>
                </div>
                <small class="rmt-form__help">{{ __('How long a quoted rate stays locked. 1–1440 minutes.') }}</small>
                @error('quote_ttl_minutes')<div class="rmt-form__error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Section 6: Status --}}
    <div class="rmt-form__card">
        <div class="rmt-form__status">
            <div class="rmt-form__status-text">
                <h6 class="rmt-form__section-title mb-1">{{ __('Corridor Status') }}</h6>
                <p class="rmt-form__section-hint mb-0">{{ __('Disabled corridors are hidden from end users instantly but keep all historical transfers intact.') }}</p>
            </div>
            <label class="rmt-form__switch">
                <input class="rmt-form__switch-input" type="checkbox" name="status" value="1"
                       @checked(old('status', $corridor->status ?? true))>
                <span class="rmt-form__switch-track"></span>
                <span class="rmt-form__switch-label" data-on="{{ __('Active') }}" data-off="{{ __('Disabled') }}"></span>
            </label>
        </div>
    </div>

    {{-- Sticky save bar --}}
    <div class="rmt-form__bar">
        <a href="{{ route('admin.remittance.corridors.index') }}" class="btn btn-outline-secondary rmt-form__bar-cancel">
            {{ __('Cancel') }}
        </a>
        <button type="submit" class="btn btn-primary rmt-form__bar-save">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
            <span>{{ __('Save Corridor') }}</span>
        </button>
    </div>
</form>

@push('scripts')
    <script>
        (function () {
            const form = document.querySelector('.rmt-form');
            if (! form) {
                return;
            }

            // Mirror selected source currency into the fee + limits suffix labels.
            const sourceCurrency = form.querySelector('[name="source_currency"]');
            const suffixes = form.querySelectorAll('[data-fx-suffix]');
            if (sourceCurrency && suffixes.length) {
                sourceCurrency.addEventListener('change', () => {
                    const code = (sourceCurrency.value || '').toUpperCase() || 'USD';
                    suffixes.forEach(el => { el.textContent = code; });
                });
            }

            // Toggle the "checked" visual on payout-method cards.
            form.querySelectorAll('.rmt-form__method-input').forEach(input => {
                input.addEventListener('change', e => {
                    e.target.closest('.rmt-form__method').classList.toggle('rmt-form__method--on', e.target.checked);
                });
            });
        })();
    </script>
@endpush
