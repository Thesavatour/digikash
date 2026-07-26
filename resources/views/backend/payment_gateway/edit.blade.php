<form method="POST"
      action="{{ route('admin.payment.gateway.update', ['gateway' => $paymentGateway->id]) }}"
      enctype="multipart/form-data"
      class="pgm-form">
	@method('PUT')
	@csrf
	
	<div class="pgm-provider">
		<div class="pgm-provider__logo">
			<img src="{{ asset($paymentGateway->logo) }}" alt="{{ $paymentGateway->name }}" loading="lazy">
		</div>
		<div class="pgm-provider__meta">
			<span>{{ __('Payment Gateway') }}</span>
			<strong>{{ $paymentGateway->name }}</strong>
			<small>{{ Str::upper($paymentGateway->code) }}</small>
		</div>
		<span class="pgm-provider__badge {{ $paymentGateway->status ? 'pgm-provider__badge--active' : 'pgm-provider__badge--inactive' }}">
            {{ $paymentGateway->status ? __('Active') : __('Inactive') }}
        </span>
	</div>
	
	<div class="pgm-panel">
		<div class="pgm-panel__head">
			<div>
				<h3>{{ __('Gateway Profile') }}</h3>
				<p>{{ __('Visible gateway identity used across admin payment controls.') }}</p>
			</div>
		</div>
		
		<div class="pgm-field">
			<label class="form-label" for="name">{{ __('Gateway Name') }}</label>
			<input class="form-control pgm-input" name="name" id="name" value="{{ $paymentGateway->name }}" type="text" required>
		</div>
	</div>
	
	<div class="pgm-panel">
		<div class="pgm-panel__head">
			<div>
				<h3>{{ __('Credential Vault') }}</h3>
				<p>{{ __('Store API keys and provider switches used by automated payment flows.') }}</p>
			</div>
			@php
				$visibleCredentialCount = count($paymentGateway->credentials);
				$booleanCredentialKeys = ['sandbox'];
				$credentialEntries = collect($paymentGateway->credentials);
				$credentialTextEntries = $credentialEntries->reject(fn ($value, $key) => is_bool($value) || in_array($key, $booleanCredentialKeys, true));
				$credentialToggleEntries = $credentialEntries->filter(fn ($value, $key) => is_bool($value) || in_array($key, $booleanCredentialKeys, true));
			@endphp
			<span class="pgm-panel__pill">{{ trans_choice(':count field|:count fields', $visibleCredentialCount, ['count' => $visibleCredentialCount]) }}</span>
		</div>
		
		<div class="pgm-fields">
			@foreach($credentialTextEntries as $key => $value)
				@php
					$label =  ucwords(str_replace('_', ' ', $key));
				@endphp
				
				<div class="pgm-field">
					<label class="form-label" for="{{ $key }}">{{ __($label) }}</label>
					<div class="pgm-secret-field">
                            <span class="pgm-secret-field__icon">
                                <i class="fa-solid fa-key"></i>
                            </span>
						<input
							class="form-control pgm-input"
							type="text"
							name="credentials[{{ $key }}]"
							id="{{ $key }}"
							value="{{ $value }}"
							required>
					</div>
				</div>
			@endforeach

			@foreach($credentialToggleEntries as $key => $value)
				@php
					$label = ucwords(str_replace('_', ' ', $key));
					$checked = filter_var($value, FILTER_VALIDATE_BOOLEAN);
					$toggleTitle = $key === 'sandbox' ? __('Sandbox Mode') : __($label);
					$toggleHint = $key === 'sandbox'
						? __('Use the provider test environment for this gateway.')
						: __('Enable or disable this provider option.');
					$enabledLabel = $key === 'sandbox' ? __('Sandbox') : __('Enabled');
					$disabledLabel = $key === 'sandbox' ? __('Live') : __('Disabled');
				@endphp

				<input type="hidden" name="credentials[{{ $key }}]" value="0">
				<label class="pgm-toggle-row pgm-toggle-row--credential" for="{{ $key }}">
					<span class="pgm-toggle-row__copy">
						<span>{{ $toggleTitle }}</span>
						<small>{{ $toggleHint }}</small>
					</span>
					<span class="pgm-toggle-card">
						<input
							class="pgm-toggle-card__input"
							type="checkbox"
							role="switch"
							name="credentials[{{ $key }}]"
							id="{{ $key }}"
							value="1"
							@checked($checked)>
						<span class="pgm-toggle-card__track" aria-hidden="true"></span>
						<span class="pgm-toggle-card__meta">
							<span class="pgm-toggle-card__state pgm-toggle-card__state--enabled">{{ $enabledLabel }}</span>
							<span class="pgm-toggle-card__state pgm-toggle-card__state--disabled">{{ $disabledLabel }}</span>
						</span>
					</span>
				</label>
			@endforeach
		</div>
	</div>
	
	@if($paymentGateway->ipn)
		<div class="pgm-panel pgm-panel--webhook">
			<div class="pgm-panel__head">
				<div>
					<h3>{{ __('Webhook Endpoint') }}</h3>
					<p>{{ __('Use this URL in the provider dashboard to receive gateway payment events.') }}</p>
				</div>
				<span class="pgm-panel__pill pgm-panel__pill--success">
                    <i class="fa-light fa-info-circle"></i>
                    {{ __('IPN') }}
                </span>
			</div>
			
			<div class="pgm-field mb-0">
				<label class="form-label" for="ipn_url">{{ __('Webhook URL') }}</label>
				<div class="input-group pgm-copy-group">
					<input class="form-control pgm-input" id="ipn_url" value="{{ url('ipn/'.$paymentGateway->code) }}" type="text" readonly required>
					<span class="btn btn-outline-info copyNow modal-tooltip"
					      data-clipboard-target="#ipn_url"
					      data-coreui-placement="top"
					      data-coreui-toggle="tooltip"
					      title="{{ __('Copy Webhook URL') }}">
                        <i class="fa-solid fa-copy"></i>
                    </span>
				</div>
				<small class="pgm-help">{{ __('Set this webhook in :gateway developer webhooks section.', ['gateway' => ucfirst($paymentGateway->name)]) }}</small>
			</div>
		</div>
	@endif
	
	@php
		$storedSandboxMode = (string) ($paymentGateway->credentials['sandbox'] ?? '0') === '1';
	@endphp
	<div class="pgm-panel pgm-connection-check">
		<div class="pgm-panel__head">
			<div>
				<h3>{{ __('Credential Health Check') }}</h3>
				<p>{{ __('Validate API credentials against the selected provider environment before saving changes.') }}</p>
			</div>
			<span class="pgm-panel__pill {{ $storedSandboxMode ? 'pgm-panel__pill--warning' : 'pgm-panel__pill--success' }}">
                <i class="fa-light fa-signal-stream"></i>
                {{ $storedSandboxMode ? __('Sandbox Stored') : __('Live Stored') }}
            </span>
		</div>
		
		<div class="pgm-check-control">
			<div class="pgm-mode-group" role="group" aria-label="{{ __('Credential test environment') }}">
				<input class="btn-check" type="radio" name="test_mode" id="test_mode_current" value="current" checked>
				<label class="pgm-mode-option" for="test_mode_current">
					<span>{{ __('Current') }}</span>
					<small>{{ __('Use saved mode') }}</small>
				</label>
				
				<input class="btn-check" type="radio" name="test_mode" id="test_mode_live" value="live">
				<label class="pgm-mode-option" for="test_mode_live">
					<span>{{ __('Live') }}</span>
					<small>{{ __('Production network') }}</small>
				</label>
				
				<input class="btn-check" type="radio" name="test_mode" id="test_mode_sandbox" value="sandbox">
				<label class="pgm-mode-option" for="test_mode_sandbox">
					<span>{{ __('Sandbox') }}</span>
					<small>{{ __('Test network') }}</small>
				</label>
			</div>
			
			<button class="btn btn-outline-primary pgm-test-btn"
			        type="button"
			        data-gateway-test
			        data-test-url="{{ route('admin.payment.gateway.test', ['gateway' => $paymentGateway->id]) }}"
			        data-testing-label="{{ __('Checking...') }}"
			        data-testing-message="{{ __('Validating gateway credentials...') }}"
			        data-fallback-message="{{ __('Unable to validate this gateway right now.') }}">
                <span class="pgm-test-btn__icon" aria-hidden="true">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
				<span class="pgm-test-btn__content">
                    <strong data-gateway-test-label>{{ __('Validate Credentials') }}</strong>
                    <small>{{ __('API health check') }}</small>
                </span>
			</button>
		</div>
		
		<div class="pgm-test-result d-none" data-gateway-test-result></div>
	</div>
	
	<div class="pgm-panel">
		<label class="pgm-toggle-row pgm-toggle-row--status" for="status">
			<span class="pgm-toggle-row__copy">
				<span>{{ __('Gateway Status') }}</span>
				<small>{{ __('Enable or disable this gateway for payment processing.') }}</small>
			</span>
			<span class="pgm-toggle-card">
				<input class="pgm-toggle-card__input" type="checkbox" role="switch" name="status" id="status" value="1" @checked($paymentGateway->status)>
				<span class="pgm-toggle-card__track" aria-hidden="true"></span>
				<span class="pgm-toggle-card__meta">
					<span class="pgm-toggle-card__state pgm-toggle-card__state--enabled">{{ __('Active') }}</span>
					<span class="pgm-toggle-card__state pgm-toggle-card__state--disabled">{{ __('Inactive') }}</span>
				</span>
			</span>
		</label>
	</div>
	
	<div class="pgm-form__footer">
		<button class="btn btn-primary pgm-submit" type="submit">
			<x-icon name="check" height="18" width="18"/>
			{{ __('Update Credentials') }}
		</button>
	</div>
</form>
