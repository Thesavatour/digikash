<div class="p2p-offers-panel p2p-accounts-panel">
	<div class="p2p-offers-panel__head p2p-accounts-panel__head">
		<div class="p2p-accounts-panel__lead">
			<div class="p2p-accounts-panel__title-row">
				<span class="p2p-accounts-panel__title-icon"><i class="fas fa-wallet"></i></span>
				<div class="p2p-accounts-panel__title-copy">
					<h6 class="p2p-offers-panel__title mb-0">@lang('Saved Payment Accounts')</h6>
					<p class="p2p-accounts-panel__subtitle">@lang('Payment details used during orders and verification.')</p>
				</div>
			</div>
		</div>
	</div>
	
	<div class="p2p-offers-panel__body p2p-accounts-panel__body">
		@forelse($accountCards as $accountCard)
			<div class="p2p-account-card">
				<div class="p2p-account-card__top">
					<div class="p2p-account-card__identity">
						<span class="p2p-account-card__logo-shell">
							@if($accountCard['payment_method_logo_url'])
								<img src="{{ $accountCard['payment_method_logo_url'] }}" alt="{{ $accountCard['payment_method_name'] }}" class="p2p-method-logo" loading="lazy">
							@else
								<span class="p2p-method-fallback">{{ $accountCard['payment_method_initial'] }}</span>
							@endif
						</span>
						
						<div class="p2p-account-card__copy">
							<div class="p2p-account-label">{{ $accountCard['payment_method_name'] }}</div>
							@if($accountCard['shows_account_display_name'])
								<div class="p2p-account-card__nickname">{{ $accountCard['account_display_name'] }}</div>
							@endif
							<div class="p2p-account-meta">
								<i class="fas fa-map-marker-alt" aria-hidden="true"></i>
								@if($accountCard['country_label'])
									<span>{{ $accountCard['country_label'] }}</span>
								@else
									<span>@lang('Global availability')</span>
								@endif
							</div>
						</div>
					</div>
					
					<div class="p2p-account-card__meta-badges">
						<span class="p2p-account-card__badge"><i class="fas fa-check-circle" aria-hidden="true"></i> @lang('Trade Ready')</span>
					</div>
				</div>
				@if($accountCard['details_preview'] !== [])
					<div class="p2p-account-details mt-2">
						@foreach($accountCard['details_preview'] as $detail)
							<div class="p2p-account-details__row">
								<span class="p2p-account-details__label">{{ $detail['label'] ?? '-' }}</span>
								<span class="p2p-account-value" title="{{ (string) ($detail['value'] ?? '') }}">
                                    {{ \Illuminate\Support\Str::limit((string) ($detail['value'] ?? ''), 28) }}
                                </span>
							</div>
						@endforeach
					</div>
				@endif
				
				<div class="p2p-account-card__actions">
					<button type="button" class="btn btn-primary btn-sm p2p-action-btn js-edit-payment-account" data-account-id="{{ $accountCard['account']->id }}">
						<i class="fas fa-pen" aria-hidden="true"></i>
						<span>@lang('Edit')</span>
					</button>
					<form
						method="POST"
						action="{{ route('user.p2p.payment-accounts.destroy', $accountCard['account']) }}"
						class="js-delete-payment-account-form"
						data-account-label="{{ $accountCard['account_display_name'] }}"
					>
						@csrf
						@method('DELETE')
						<button type="submit" class="btn btn-danger btn-sm p2p-action-btn text-white">
							<i class="fas fa-trash-alt" aria-hidden="true"></i>
							<span>@lang('Delete')</span>
						</button>
					</form>
				</div>
			</div>
		@empty
			<x-user-not-found
				class="p2p-accounts-empty"
				:title="__('No accounts added yet')"
				:message="__('Add your payment accounts first so order instructions and verification stay ready from the first trade.')"
				:eyebrow="__('P2P payments')"
				icon="fa-wallet"
				:action-url="route('user.p2p.payment-accounts.index', ['create' => 1])"
				:action-label="__('Add My First Account')"
				action-icon="fa-plus"
			/>
		@endforelse
	</div>
</div>
