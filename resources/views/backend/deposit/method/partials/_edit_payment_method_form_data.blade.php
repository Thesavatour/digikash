@php use App\Enums\FixPctType; @endphp
<form action="{{ route('admin.deposit.method.update', $paymentMethod->id) }}" method="post"
      enctype="multipart/form-data">
	@method('PUT')
	@csrf
	<input type="hidden" name="type" value="{{ $paymentMethod->type }}">
	<div class="row mb-3">
		<div class="col-lg-6 col-md-6 col-12">
			<label class="form-label" for="icon">{{ __('Logo') }}</label>
			<x-img name="logo" old="{{ $paymentMethod->logo_alt }}" :ref="'coevs-payment-method-logo'"/>
		</div>
	</div>
	@if($paymentMethod->type === App\Enums\MethodType::AUTOMATIC)
		<div class="row mb-3">
			<div class="col-lg-6 col-md-6 col-12">
				<label class="form-label" for="role">{{ __('Payment Gateway') }}</label>
				<select class="form-select" id="select-payment-gateway" name="payment_gateway_id" required>
					<option selected disabled>{{ __('Select Payment Gateway') }}</option>
					@foreach($paymentGateways as $gateway)
						<option value="{{ $gateway->id }}" @selected($paymentMethod->payment_gateway_id === $gateway->id)>{{ $gateway->name }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
				<label class="form-label" for="currency">{{ __('Supported Currency') }}</label>
				<select class="form-select" id="currency-list" name="currency" required>
					@foreach($paymentMethod->paymentGateway->currencies as $paymentCurrency)
						<option value="{{ $paymentCurrency }}" @selected($paymentMethod->currency === $paymentCurrency)>{{ $paymentCurrency }}</option>
					@endforeach
				</select>
			</div>
			@include('backend.partials._automatic_method_code_notice')
		</div>
	@endif
	<div class="row mb-3">
		<div class="col-lg-6 col-md-6 col-12">
			<label class="form-label" for="name">{{ __('Name') }}</label>
			<input class="form-control" type="text" value="{{ $paymentMethod->name }}" name="name" placeholder="Name"
			       required>
		</div>
		<div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
			<label class="form-label" for="currency_symbol">{{ __('Currency Symbol') }}</label>
			<input class="form-control" type="text" name="currency_symbol" value="{{ $paymentMethod->currency_symbol }}"
			       id="currency-symbol" placeholder="Ex: $, BTC"
			       required>
		</div>
	</div>
	@if($paymentMethod->type == App\Enums\MethodType::MANUAL)
		<div class="row mb-3">
			<div class="col-lg-6 col-md-6 col-12">
				@include('backend.partials._method_code_uniqueness_hint', ['value' => $paymentMethod->method_code, 'placeholder' => 'Ex: natcash-htg', 'context' => 'deposit'])
			</div>
			<div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
				<div class="mb-3">
					<label class="form-label" for="currency">{{ __('Currency') }}</label>
					<input class="form-control" type="text" name="currency" id="custom_currency"
					       value="{{ $paymentMethod->currency }}"
					       placeholder="Ex: USD, BTC,etc.."
					       required>
				</div>
				<div>
					<label class="form-label" for="conversion_rate">{{ __('Conversion Rate:') }}</label>
					<div class="input-group">
						<span class="input-group-text">1 {{ siteCurrency() }} =</span>
						<input type="text" oninput="this.value = validateDouble(this.value)"
						       name="conversion_rate" value="{{ $paymentMethod->conversion_rate }}" id="conversion_rate"
						       class="form-control"
						       aria-label="Amount (to the nearest dollar)">
						<span class="input-group-text" id="currency-selected">{{ $paymentMethod->currency }}</span>
					</div>
				</div>
			</div>
		</div>
	@endif
	@if($paymentMethod->type == App\Enums\MethodType::AUTOMATIC)
		<div class="row mb-3">
			<div class="col-md-12">
				<label class="form-label" for="conversion_rate">{{ __('Conversion Rate:') }}</label>
				<a class="badge text-bg-secondary text-decoration-none"
				   href="{{ route('admin.settings.plugin_type','exchange_rate') }}"> {{ __('Manage Exchange') }}</a>
				<div class="input-group">
					<span class="input-group-text">1 {{ siteCurrency() }} =</span>
					<input type="text" oninput="this.value = validateDouble(this.value)"
					       name="conversion_rate" value="{{ $paymentMethod->conversion_rate }}" id="conversion_rate"
					       class="form-control"
					       aria-label="Amount (to the nearest dollar)">
					<span class="input-group-text">
                        <div class="form-check form-switch">
                          <input type="hidden" name="conversion_rate_live" value="0">
                          <input class="form-check-input" id="conversion_rate_live" type="checkbox"
                                 @checked($paymentMethod->conversion_rate_live)
                                 name="conversion_rate_live" value="1">
                          <label class="form-check-label text-danger" for="conversion_rate_live">
                            {{ __('Live') }}
                          </label>
                        </div>
                    </span>
					<span class="input-group-text" id="currency-selected">{{ $paymentMethod->currency }}</span>
				</div>
			</div>
		</div>
	@endif
	<div class="row mb-3">
		<div class="col-lg-6 col-md-6 col-12 mt-md-0 mt-3">
			<label class="form-label" for="min_deposit">{{ __('Minimum Deposit:') }}</label>
			<div class="input-group">
				<input type="text" class="form-control" name="min_deposit" value="{{ $paymentMethod->min_deposit }}"
				       oninput="this.value = validateDouble(this.value)"
				       aria-label="Amount (to the nearest dollar)">
				<span class="input-group-text">{{ siteCurrency() }}</span>
			</div>
		</div>
		<div class="col-lg-6 col-md-6 col-12">
			<label class="form-label" for="max_deposit">{{ __('Maximum Deposit:') }}</label>
			<div class="input-group">
				<input type="text" class="form-control" name="max_deposit" value="{{ $paymentMethod->max_deposit }}"
				       oninput="this.value = validateDouble(this.value)"
				       aria-label="Amount (to the nearest dollar)">
				<span class="input-group-text">{{ siteCurrency() }}</span>
			</div>
		</div>
	</div>
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
						       name="user_charge" value="{{ $paymentMethod->user_charge }}" placeholder="{{ __('Amount') }}">
						<select name="user_charge_type" class="form-select input-group-select">
							@foreach(FixPctType::options() as $key => $value)
								<option value="{{ $key }}" @selected($key == $paymentMethod->user_charge_type?->value)>{{ $value }}</option>
							@endforeach
						</select>
					</div>
					<small class="charge-config__hint">{{ __('Charge applied to regular user deposits') }}</small>
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
						       name="merchant_charge" value="{{ $paymentMethod->merchant_charge }}" placeholder="{{ __('Amount') }}">
						<select name="merchant_charge_type" class="form-select input-group-select">
							@foreach(FixPctType::options() as $key => $value)
								<option value="{{ $key }}" @selected($key == $paymentMethod->merchant_charge_type?->value)>{{ $value }}</option>
							@endforeach
						</select>
					</div>
					<small class="charge-config__hint">{{ __('Charge applied to merchant user deposits') }}</small>
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
						       name="agent_charge" value="{{ $paymentMethod->agent_charge }}" placeholder="{{ __('Amount') }}">
						<select name="agent_charge_type" class="form-select input-group-select">
							@foreach(FixPctType::options() as $key => $value)
								<option value="{{ $key }}" @selected($key == $paymentMethod->agent_charge_type?->value)>{{ $value }}</option>
							@endforeach
						</select>
					</div>
					<small class="charge-config__hint">{{ __('Optional — defaults to the regular user charge for agent deposits') }}</small>
				</div>
			</div>
		</div>
	</div>
	@if($paymentMethod->type == App\Enums\MethodType::MANUAL)
		<div class="dynamic-fields mb-3 mt-4">
			<div class="dynamic-fields__head">
				<span class="dynamic-fields__icon"><i class="fa-solid fa-list-check"></i></span>
				<div class="dynamic-fields__titles">
					<h6 class="dynamic-fields__title">{{ __('Customer Payment Fields') }}</h6>
					<span
						class="dynamic-fields__sub">{{ __('Details the customer enters after paying — e.g. Sender Number, Transaction ID, or a payment screenshot. These build the deposit form shown for this method.') }}</span>
				</div>
				<a href="javascript:void(0)" id="add-new-field" class="dynamic-fields__add add-new-field">
					<i class="fa-solid fa-circle-plus"></i> {{ __('Add Field') }}
				</a>
			</div>
			
			<div class="dynamic-fields__cols row d-none d-xl-flex">
				<div class="col-xl-4">{{ __('Field Label') }}</div>
				<div class="col-xl-4">{{ __('Field Type') }}</div>
				<div class="col-xl-3">{{ __('Requirement') }}</div>
				<div class="col-xl-1"></div>
			</div>
			
			<div class="append-new-field-edit dynamic-fields__list"
			     data-empty="{{ __('No fields yet. Click Add Field to collect payment details from the customer.') }}">@foreach($paymentMethod->fields as $key => $value)
					@include('backend.deposit.method.partials._method_append_form_field', ['key' => $key, 'field' => $value])
				@endforeach</div>
		</div>
		
		<div class="mt-3">
			<label for="receive_payment_details" class="form-label fw-semibold mb-1">{{ __('Receive Payment Details') }}</label>
			<span class="d-block text-muted small mb-2">{{ __('Instructions shown to the customer — your wallet/account number, network, and any notes.') }}</span>
			<div class="site-editor">
				<textarea class="summernote form-control" id="receive_payment_details" name="receive_payment_details">{!! $paymentMethod->receive_payment_details !!}</textarea>
			</div>
		</div>
	@endif
	<div class="row mb-3 mt-3">
		<div class="col-12">
			<label class="admin-switch-card">
				<input class="admin-switch-card__input" type="checkbox" role="switch" name="status"
				       @checked($paymentMethod->status) value="1">
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
			<x-icon name="check" height="20"/> {{ __('Update Payment Method') }}
		</button>
	</div>
</form>
