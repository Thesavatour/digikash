@php use App\Enums\FixPctType; @endphp
@php use App\Constants\TimeUnits; @endphp
<div class="modal fade payment-method-modal" id="new_payment_method_modal" aria-hidden="true" aria-labelledby="logoutmodal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Add New').' '. $type->label().' ' . __('Payment Method') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body payment-method-form">
                <form action="{{ route('admin.withdraw.method.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <div class="row mb-3">
                        <div class="col-lg-6 col-md-6 col-12">
                            <label class="form-label" for="icon">{{ __('Logo') }}</label>
                            <x-img name="logo"/>
                        </div>
                    </div>
                    @if($type === App\Enums\MethodType::AUTOMATIC)
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-12">
                                <label class="form-label" for="role">{{ __('Payment Gateway') }}</label>
                                <select class="form-select" id="select-payment-gateway" name="payment_gateway_id"
                                        required>
                                    <option selected disabled>{{ __('Select Payment Gateway') }}</option>
                                    @foreach($paymentGateways as $gateway)
                                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
                                <label class="form-label" for="currency">{{ __('Supported Currency') }}</label>
                                <select class="form-select" id="currency-list" name="currency" required>
                                    <option selected disabled>{{ __('Select First Payment Gateway') }}</option>
                                </select>
                            </div>
                            @include('backend.partials._automatic_method_code_notice')
                        </div>
                    @endif
                    <div class="row mb-3">
                        <div class="col-lg-6 col-md-6 col-12">
                            <label class="form-label" for="name">{{ __('Name') }}</label>
                            <input class="form-control" type="text" name="name" placeholder="Name" required>
                        </div>
                        <div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
                            <label class="form-label" for="currency_symbol">{{ __('Currency Symbol') }}</label>
                            <input class="form-control" type="text" name="currency_symbol" id="currency-symbol"
                                   placeholder="Ex: $, BTC"
                                   required>
                        </div>
                    </div>
                    @if($type == App\Enums\MethodType::MANUAL)
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-12">
                                @include('backend.partials._method_code_uniqueness_hint', ['placeholder' => 'Ex: natcash-htg-payout', 'context' => 'withdraw'])
                            </div>

                            <div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
                                <div class="mb-3">
                                    <label class="form-label" for="currency">{{ __('Currency') }}</label>
                                    <input class="form-control" type="text" name="currency" id="custom_currency"
                                           placeholder="Ex: USD, BTC,etc.."
                                           required>
                                </div>
                                <div>
                                    <label class="form-label" for="conversion_rate">{{ __('Conversion Rate:') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ siteCurrency() }}</span>
                                        <input type="text" oninput="this.value = validateDouble(this.value)"
                                               name="conversion_rate" id="conversion_rate" class="form-control"
                                               aria-label="Amount (to the nearest dollar)">
                                        <span class="input-group-text" id="currency-selected"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if($type == App\Enums\MethodType::AUTOMATIC)
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label" for="conversion_rate">{{ __('Conversion Rate:') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ siteCurrency() }}</span>
                                    <input type="text" oninput="this.value = validateDouble(this.value)"
                                           name="conversion_rate" id="conversion_rate" class="form-control"
                                           aria-label="Amount (to the nearest dollar)">
                                    <span class="input-group-text">
                                        <div class="form-check form-switch">
                                          <input type="hidden" name="conversion_rate_live" value="0">
                                          <input class="form-check-input" id="conversion_rate_live" type="checkbox"
                                                 name="conversion_rate_live" value="1">
                                          <label class="form-check-label text-danger" for="conversion_rate_live">
                                            {{ __('Live') }}
                                          </label>
                                        </div>
                                    </span>
                                    <span class="input-group-text" id="currency-selected"></span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-lg-6 col-md-6 col-12">
                            <label class="form-label" for="currency_symbol">{{ __('Minimum Withdraw:') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="min_withdraw"
                                       oninput="this.value = validateDouble(this.value)"
                                       aria-label="Amount (to the nearest dollar)">
                                <span class="input-group-text">{{ siteCurrency() }}</span>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
                            <label class="form-label" for="currency_symbol">{{ __('Maximum Withdraw:') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="max_withdraw"
                                       oninput="this.value = validateDouble(this.value)"
                                       aria-label="Amount (to the nearest dollar)">
                                <span class="input-group-text">{{ siteCurrency() }}</span>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Charge Configuration --}}
                    <div class="charge-config mb-4">
                        <div class="charge-config__head">
                            <span class="charge-config__icon"><i class="fas fa-calculator"></i></span>
                            <h6 class="charge-config__title">{{ __('Charge Configuration') }}</h6>
                        </div>

                        <div class="charge-config__note">
                            <span class="method-code-panel__dot" aria-hidden="true"></span>
                            <span>{{ __('Set separate fees for regular users and merchants. Merchants often get preferential rates.') }}</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <div class="charge-config__card charge-config__card--user">
                                    <label class="charge-config__label">
                                        <i class="fas fa-user"></i>{{ __('Regular User Charge') }}
                                    </label>
                                    <div class="input-group">
                                        <input class="form-control" type="text"
                                               oninput="this.value = validateDouble(this.value)"
                                               name="user_charge" placeholder="{{ __('Amount') }}">
                                        <select name="user_charge_type" class="form-select input-group-select">
                                            @foreach(FixPctType::options() as $key => $value)
                                                <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <small class="charge-config__hint">{{ __('Charge applied to regular user withdrawals') }}</small>
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <div class="charge-config__card charge-config__card--merchant">
                                    <label class="charge-config__label">
                                        <i class="fas fa-store"></i>{{ __('Merchant User Charge') }}
                                    </label>
                                    <div class="input-group">
                                        <input class="form-control" type="text"
                                               oninput="this.value = validateDouble(this.value)"
                                               name="merchant_charge" placeholder="{{ __('Amount') }}">
                                        <select name="merchant_charge_type" class="form-select input-group-select">
                                            @foreach(FixPctType::options() as $key => $value)
                                                <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <small class="charge-config__hint">{{ __('Charge applied to merchant user withdrawals') }}</small>
                                </div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <div class="charge-config__card charge-config__card--agent">
                                    <label class="charge-config__label">
                                        <i class="fas fa-user-tie"></i>{{ __('Agent User Charge') }}
                                    </label>
                                    <div class="input-group">
                                        <input class="form-control" type="text"
                                               oninput="this.value = validateDouble(this.value)"
                                               name="agent_charge" placeholder="{{ __('Amount') }}">
                                        <select name="agent_charge_type" class="form-select input-group-select">
                                            @foreach(FixPctType::options() as $key => $value)
                                                <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <small class="charge-config__hint">{{ __('Optional — defaults to the regular user charge for agent withdrawals') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($type == App\Enums\MethodType::MANUAL)
                        <div class="row mb-3">
                            <div class="col-lg-6 col-md-6 col-12">
                                <label class="form-label" for="process_time_value">{{ __('Process Time:') }}</label>
                                <div class="input-group">
                                    <input class="form-control" type="text" id="process_time_value"
                                           oninput="this.value = validateNumber(this.value)"
                                           name="process_time_value" placeholder="{{ __('e.g. 24') }}" required>
                                    <select name="process_time_unit" class="form-select input-group-select">
                                        @foreach(TimeUnits::getAll() as $unit)
                                            <option value="{{ $unit }}">{{ $unit }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <span class="d-block text-muted small mt-1">{{ __('How long this payout usually takes to process.') }}</span>
                            </div>
                        </div>

                        <div class="dynamic-fields mb-3">
                            <div class="dynamic-fields__head">
                                <span class="dynamic-fields__icon"><i class="fa-solid fa-list-check"></i></span>
                                <div class="dynamic-fields__titles">
                                    <h6 class="dynamic-fields__title">{{ __('Customer Payout Fields') }}</h6>
                                    <span class="dynamic-fields__sub">{{ __('Details the customer enters when requesting a withdrawal — e.g. Wallet Address, Account Number, or Bank Name. These build the withdrawal form for this method.') }}</span>
                                </div>
                                <a href="javascript:void(0)" id="add-new-field" class="dynamic-fields__add">
                                    <i class="fa-solid fa-circle-plus"></i> {{ __('Add Field') }}
                                </a>
                            </div>

                            <div class="dynamic-fields__cols row d-none d-xl-flex">
                                <div class="col-xl-4">{{ __('Field Label') }}</div>
                                <div class="col-xl-4">{{ __('Field Type') }}</div>
                                <div class="col-xl-3">{{ __('Requirement') }}</div>
                                <div class="col-xl-1"></div>
                            </div>

                            <div class="append-new-field dynamic-fields__list" data-empty="{{ __('No fields yet. Click Add Field to collect payout details from the customer.') }}"></div>
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="admin-switch-card">
                                <input class="admin-switch-card__input" type="checkbox" role="switch" name="status" value="1">
                                <span class="admin-switch-card__track" aria-hidden="true"></span>
                                <span class="admin-switch-card__copy">
                                    <strong>{{ __('Status') }}</strong>
                                    <small>{{ __('Enable to make this method available to users.') }}</small>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="payment-method-actions">
                        <button class="btn btn-primary" type="submit">
                            <x-icon name="check" height="20"/>
                            {{ __('Create Payment Method') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
