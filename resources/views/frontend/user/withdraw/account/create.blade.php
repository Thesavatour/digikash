@extends('frontend.layouts.user.index')
@section('title', __('Create Withdraw Account'))
@section('content')
    @php
        $selectedMethod = $withdrawMethods->firstWhere('id', (int) old('method_id'));
        $selectedCurrency = old('currency', '');
    @endphp
    <div class="single-form-card withdraw-account-create">
        <x-user-feature-header
            :title="__('Create Account')"
            :subtitle="__('Add a verified payout destination for future withdrawals.')"
            icon="fas fa-plus-circle"
        >
            <a class="btn btn-light-primary btn-sm" href="{{ route('user.withdraw.account.index') }}">
                <i class="fa-solid fa-receipt"></i> {{ __('My Accounts') }}
            </a>
        </x-user-feature-header>
        <div class="card-main bg-main withdraw-account-create__body">
            @if($currencyOptions->isEmpty())
                <x-user-not-found
                    :title="__('No currencies available')"
                    :message="__('Create or activate a wallet before adding a withdrawal account.')"
                    icon="fa-wallet"
                    :action-url="route('user.wallet.index')"
                    :action-label="__('Manage Wallets')"
                    action-icon="fa-wallet"
                />
            @elseif($withdrawMethods->isEmpty())
                <x-user-not-found
                    :title="__('No payment methods available')"
                    :message="__('No active withdrawal payment methods are available right now. Please try again later.')"
                    icon="fa-credit-card"
                />
            @else
                <form action="{{ route('user.withdraw.account.store') }}"
                      method="POST"
                      enctype="multipart/form-data"
                      class="withdraw-account-create__form"
                      data-withdraw-account-form
                      data-fields-url-template="{{ route('user.withdraw.credentials.fields', ['method_id' => '__METHOD_ID__']) }}"
                      data-loading-text="{{ __('Loading credential fields...') }}"
                      data-error-text="{{ __('Unable to load credential fields. Please choose another method or try again.') }}"
                      data-no-methods-text="{{ __('No withdrawal methods are available for this currency.') }}">
                    @csrf
                    <div class="withdraw-account-create__steps" aria-hidden="true">
                        <span class="withdraw-account-create__step is-active">
                            <span><i class="fa-solid fa-coins"></i></span> {{ __('Currency') }}
                        </span>
                        <span class="withdraw-account-create__step">
                            <span><i class="fa-solid fa-building-columns"></i></span> {{ __('Method') }}
                        </span>
                        <span class="withdraw-account-create__step">
                            <span><i class="fa-solid fa-id-card"></i></span> {{ __('Details') }}
                        </span>
                    </div>

                    <div class="row g-3 withdraw-account-create__grid">
                        <div class="col-lg-4 col-md-6">
                            <div class="withdraw-account-create__field">
                                <label for="currency-select" class="form-label">{{ __('Currency') }}</label>
                                <div class="withdraw-account-create__control">
                                    <span class="withdraw-account-create__control-icon" aria-hidden="true">
                                        <i class="fa-solid fa-layer-group"></i>
                                    </span>
                                    <select class="form-select @error('currency') is-invalid @enderror"
                                            id="currency-select"
                                            name="currency"
                                            data-withdraw-currency
                                            required>
                                        <option value="" disabled @selected(blank($selectedCurrency))>{{ __('Select Currency') }}</option>
                                        @foreach($currencyOptions as $currency)
                                            <option value="{{ $currency->code }}" @selected($selectedCurrency === $currency->code)>
                                                {{ $currency->name }} ({{ $currency->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('currency')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="withdraw-account-create__field">
                                <label for="method-select" class="form-label">{{ __('Withdrawal Method') }}</label>
                                <div class="withdraw-account-create__control">
                                    <span class="withdraw-account-create__control-icon" aria-hidden="true">
                                        <i class="fa-solid fa-building-columns"></i>
                                    </span>
                                    <select class="form-select @error('method_id') is-invalid @enderror"
                                            id="method-select"
                                            name="method_id"
                                            data-withdraw-method
                                            data-placeholder-default="{{ __('Select a currency first') }}"
                                            data-placeholder-ready="{{ __('Select Withdrawal Method') }}"
                                            data-placeholder-empty="{{ __('No withdrawal methods for this currency') }}"
                                            @disabled(blank($selectedCurrency))
                                            required>
                                        <option value="" disabled @selected(blank(old('method_id')))>
                                            {{ blank($selectedCurrency) ? __('Select a currency first') : __('Select Withdrawal Method') }}
                                        </option>
                                        @foreach($withdrawMethods as $method)
                                            <option value="{{ $method->id }}"
                                                    data-currency="{{ $method->currency }}"
                                                    @selected((int) old('method_id') === (int) $method->id)
                                                    @class(['d-none' => $selectedCurrency !== '' && $selectedCurrency !== $method->currency])>
                                                {{ title($method->name) }} - {{ $method->currency }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('method_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-12">
                            <div class="withdraw-account-create__field">
                                <label for="accountName" class="form-label">{{ __('Account Name') }}</label>
                                <div class="withdraw-account-create__control">
                                    <span class="withdraw-account-create__control-icon" aria-hidden="true">
                                        <i class="fa-solid fa-id-card"></i>
                                    </span>
                                    <input type="text"
                                           class="form-control @error('account_name') is-invalid @enderror"
                                           id="accountName"
                                           name="account_name"
                                           value="{{ old('account_name') }}"
                                           placeholder="{{ __('Enter Account Name') }}"
                                           required>
                                </div>
                                @error('account_name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 withdraw-account-create__credentials" id="credential-fields">
                        @if($selectedMethod && $selectedCurrency === $selectedMethod->currency)
                            @include('frontend.user.withdraw.partials.credentials_fields', ['method' => $selectedMethod])
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary withdraw-account-create__submit">
                        <i class="fa-solid fa-plus-circle" aria-hidden="true"></i>
                        {{ __('Create Account') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('frontend/js/withdraw-account.js?v=' . config('app.version') . '-' . filemtime(public_path('frontend/js/withdraw-account.js'))) }}"></script>
@endpush
