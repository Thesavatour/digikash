@php
    use App\Enums\PayoutMethod;
@endphp

<form action="{{ $action }}" method="POST" id="dk-rmt-bene-form" onsubmit="disableSubmitButton(this, '{{ __('Saving...') }}')">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Section 1: Who --}}
    <div class="dk-rmt__card mb-3">
        <div class="d-flex gap-3 mb-3 align-items-start">
            <span class="dk-rmt__iconbox dk-rmt__iconbox--sm"><span style="font-weight:800;font-size:12px;">1</span></span>
            <div>
                <div style="font-weight:700;font-size:15px;color:var(--dk-rmt-heading);">{{ __('Who are they?') }}</div>
                <div style="font-size:12px;color:var(--dk-rmt-muted);">{{ __('Your recipient\'s legal name as on their ID and bank records.') }}</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="dk-rmt__label">{{ __('First name') }}</label>
                <input type="text" class="dk-rmt__input" name="first_name"
                       value="{{ old('first_name', $beneficiary?->first_name) }}" required>
                @error('first_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="dk-rmt__label">{{ __('Last name') }}</label>
                <input type="text" class="dk-rmt__input" name="last_name"
                       value="{{ old('last_name', $beneficiary?->last_name) }}" required>
                @error('last_name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="dk-rmt__label">{{ __('Nickname (optional)') }}</label>
                <input type="text" class="dk-rmt__input" name="nickname"
                       placeholder="{{ __('e.g. Mom') }}"
                       value="{{ old('nickname', $beneficiary?->nickname) }}">
            </div>
            <div class="col-md-6">
                <label class="dk-rmt__label">{{ __('Relationship (optional)') }}</label>
                <select class="dk-rmt__select" name="relationship">
                    <option value="">{{ __('Select') }}</option>
                    @foreach (['Family', 'Friend', 'Self', 'Business'] as $opt)
                        <option value="{{ $opt }}" @selected(old('relationship', $beneficiary?->relationship) === $opt)>{{ __($opt) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-7">
                <label class="dk-rmt__label">{{ __('Country') }}</label>
                <select class="dk-rmt__select" name="country_code" required>
                    <option value="">{{ __('Select country') }}</option>
                    @foreach (getCountries() ?: [] as $country)
                        <option value="{{ $country['code'] }}"
                                @selected(strtoupper(old('country_code', $beneficiary?->country_code ?? '')) === $country['code'])>
                            {{ getCountryFlagEmoji($country['code']) }} {{ $country['name'] }} ({{ $country['code'] }})
                        </option>
                    @endforeach
                </select>
                @error('country_code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="dk-rmt__label">{{ __('Currency') }}</label>
                <input type="text" class="dk-rmt__input dk-mono text-uppercase" name="currency" maxlength="3"
                       placeholder="BDT"
                       value="{{ old('currency', $beneficiary?->currency) }}" required>
                @error('currency')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="dk-rmt__favorite" for="is_favorite">
                    <input type="checkbox" name="is_favorite" value="1" id="is_favorite"
                           class="dk-rmt__favorite-input"
                           @checked(old('is_favorite', $beneficiary?->is_favorite))>
                    <span class="dk-rmt__favorite-card">
                        <span class="dk-rmt__favorite-icon" aria-hidden="true">
                            <i class="fa-solid fa-star"></i>
                        </span>
                        <span class="dk-rmt__favorite-body">
                            <span class="dk-rmt__favorite-title">{{ __('Mark as favorite') }}</span>
                            <span class="dk-rmt__favorite-sub">{{ __('Pin this beneficiary to the top of your list for faster sending next time.') }}</span>
                        </span>
                        <span class="dk-rmt__favorite-switch" aria-hidden="true">
                            <span class="dk-rmt__favorite-switch-knob"></span>
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    {{-- Section 2: Payout method --}}
    <div class="dk-rmt__card mb-3">
        <div class="d-flex gap-3 mb-3 align-items-start">
            <span class="dk-rmt__iconbox dk-rmt__iconbox--sm"><span style="font-weight:800;font-size:12px;">2</span></span>
            <div>
                <div style="font-weight:700;font-size:15px;color:var(--dk-rmt-heading);">{{ __('How will they get paid?') }}</div>
                <div style="font-size:12px;color:var(--dk-rmt-muted);">{{ __('Pick the payout type — fields below will adapt.') }}</div>
            </div>
        </div>

        <div class="dk-rmt__methods" id="bene-method-grid">
            @foreach ($payoutMethods as $method)
                @php
                    $isOn = old('payout_method', $beneficiary?->payout_method?->value) === $method->value;
                    $sub = match ($method) {
                        PayoutMethod::BankAccount   => __('Direct bank transfer · 1–2 days'),
                        PayoutMethod::MobileWallet  => __('bKash, M-Pesa, GCash · minutes'),
                        PayoutMethod::CashPickup    => __('Pickup partner location'),
                        PayoutMethod::DebitCard     => __('Push-to-card · instant'),
                        PayoutMethod::WalletCredit  => __('Internal wallet credit'),
                    };
                @endphp
                <label class="dk-rmt__method {{ $isOn ? 'is-on' : '' }}"
                       data-method="{{ $method->value }}"
                       data-fields='@json($method->requiredFields())'>
                    <input type="radio" class="dk-rmt__method-radio" name="payout_method"
                           value="{{ $method->value }}" @checked($isOn)>
                    <span class="dk-rmt__iconbox"><i class="{{ $method->icon() }}"></i></span>
                    <span class="dk-rmt__method-body">
                        <span class="dk-rmt__method-name">{{ $method->label() }}</span>
                        <span class="dk-rmt__method-sub">{{ $sub }}</span>
                    </span>
                    <span class="dk-rmt__method-dot"></span>
                </label>
            @endforeach
        </div>
        @error('payout_method')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>

    {{-- Section 3: Account details (dynamic) --}}
    <div class="dk-rmt__card mb-3">
        <div class="d-flex gap-3 mb-3 align-items-start">
            <span class="dk-rmt__iconbox dk-rmt__iconbox--sm"><span style="font-weight:800;font-size:12px;">3</span></span>
            <div>
                <div style="font-weight:700;font-size:15px;color:var(--dk-rmt-heading);">{{ __('Account details') }}</div>
                <div style="font-size:12px;color:var(--dk-rmt-muted);">{{ __('Exact details from the recipient\'s account or wallet.') }}</div>
            </div>
        </div>

        <div class="row g-3" id="bene-account-fields"></div>
        @error('account_details')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>

    {{-- Section 4: Contact (optional) --}}
    <div class="dk-rmt__card mb-3">
        <div class="d-flex gap-3 mb-3 align-items-start">
            <span class="dk-rmt__iconbox dk-rmt__iconbox--sm"><span style="font-weight:800;font-size:12px;">4</span></span>
            <div>
                <div style="font-weight:700;font-size:15px;color:var(--dk-rmt-heading);">{{ __('Contact (optional)') }}</div>
                <div style="font-size:12px;color:var(--dk-rmt-muted);">{{ __('We may use these to notify the recipient when funds arrive.') }}</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="dk-rmt__label">{{ __('Phone number') }}</label>
                <input type="text" class="dk-rmt__input dk-mono" name="phone_number"
                       placeholder="+8801712345678"
                       autocomplete="off"
                       data-1p-ignore="true"
                       data-lpignore="true"
                       value="{{ old('phone_number', $beneficiary?->phone_number) }}">
                <div style="font-size:11.5px;color:var(--dk-rmt-muted);margin-top:5px;">{{ __('International format with country code.') }}</div>
                @error('phone_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="dk-rmt__label">{{ __('Email') }}</label>
                <input type="email" class="dk-rmt__input" name="email"
                       autocomplete="off"
                       data-1p-ignore="true"
                       value="{{ old('email', $beneficiary?->email) }}">
                @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    {{-- Action bar --}}
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('user.beneficiaries.index') }}" class="dk-rmt__btn dk-rmt__btn--ghost"
           style="background:var(--dk-rmt-soft);border:1px solid var(--dk-rmt-border);color:var(--dk-rmt-body);">
            {{ __('Cancel') }}
        </a>
        <button type="submit" class="dk-rmt__btn dk-rmt__btn--primary">
            <i class="fa-solid fa-check"></i> {{ __('Save recipient') }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        (function () {
            const form    = document.getElementById('dk-rmt-bene-form');
            if (! form) { return; }
            const grid    = document.getElementById('bene-method-grid');
            const target  = document.getElementById('bene-account-fields');
            const stored  = @json(old('account_details', $beneficiary?->account_details ?? []));

            function render(label) {
                const fields = JSON.parse(label.dataset.fields || '[]');
                target.innerHTML = '';
                fields.forEach(function (field) {
                    const col = document.createElement('div');
                    col.className = 'col-md-6';
                    const isMono = /account_number|card_number|swift|iban|wallet_uuid|phone/i.test(field.name);
                    col.innerHTML = `
                        <label class="dk-rmt__label">${field.label}${field.required ? ' *' : ''}</label>
                        <input type="text" class="dk-rmt__input ${isMono ? 'dk-mono' : ''}"
                               name="account_details[${field.name}]"
                               value="${(stored[field.name] || '').toString().replace(/"/g, '&quot;')}"
                               autocomplete="off"
                               data-1p-ignore="true"
                               ${field.required ? 'required' : ''}>
                    `;
                    target.appendChild(col);
                });
            }

            function select(label) {
                grid.querySelectorAll('.dk-rmt__method').forEach(l => l.classList.toggle('is-on', l === label));
                const input = label.querySelector('input[type="radio"]');
                if (input) { input.checked = true; }
                render(label);
            }

            grid.querySelectorAll('.dk-rmt__method').forEach(label => {
                label.addEventListener('click', e => {
                    if (e.target.tagName !== 'INPUT') {
                        select(label);
                    }
                });
                label.querySelector('input').addEventListener('change', () => select(label));
            });

            const checked = grid.querySelector('input:checked');
            if (checked) {
                render(checked.closest('.dk-rmt__method'));
            }
        })();
    </script>
@endpush
