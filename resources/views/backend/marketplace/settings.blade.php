@extends('backend.layouts.app')
@section('title', __('Marketplace Settings'))
@section('content')
	<div class="py-4">
		<h1 class="h4 mb-3">{{ __('Marketplace Settings') }}</h1>
	</div>

	<div class="card border-0 mb-4">
		<div class="card-body">
			<form action="{{ route('admin.marketplace.settings.update') }}" method="POST">
				@csrf
				@method('PUT')

				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label">{{ __('Marketplace status') }}</label>
						<select name="enabled" class="form-select">
							<option value="1" @selected($settings->enabled)>{{ __('Enabled') }}</option>
							<option value="0" @selected(! $settings->enabled)>{{ __('Disabled') }}</option>
						</select>
						<div class="form-text">{{ __('Turn the buyer/vendor marketplace on or off.') }}</div>
					</div>

					<div class="col-md-6">
						<label class="form-label">{{ __('Auto-release to vendor') }}</label>
						<select name="auto_release" class="form-select">
							<option value="1" @selected($settings->auto_release)>{{ __('Yes (instant)') }}</option>
							<option value="0" @selected(! $settings->auto_release)>{{ __('No (hold)') }}</option>
						</select>
					</div>

					<div class="col-md-4">
						<label for="commission_pct" class="form-label">{{ __('Commission %') }} <span class="text-danger">*</span></label>
						<div class="input-group">
							<input type="number" step="0.0001" min="0" max="100" name="commission_pct" id="commission_pct"
								   class="form-control @error('commission_pct') is-invalid @enderror"
								   value="{{ old('commission_pct', $settings->commission_pct) }}" required>
							<span class="input-group-text">%</span>
						</div>
						@error('commission_pct')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
						<div class="form-text">{{ __('Platform cut of each order. The rest goes to the vendor.') }}</div>
					</div>

					<div class="col-md-4">
						<label for="commission_fixed" class="form-label">{{ __('Fixed fee') }}</label>
						<input type="number" step="0.00000001" min="0" name="commission_fixed" id="commission_fixed"
							   class="form-control @error('commission_fixed') is-invalid @enderror"
							   value="{{ old('commission_fixed', $settings->commission_fixed) }}">
						@error('commission_fixed')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
						<div class="form-text">{{ __('Optional flat fee added on top of the percentage.') }}</div>
					</div>

					<div class="col-md-4">
						<label for="payout_hold_minutes" class="form-label">{{ __('Payout hold (minutes)') }}</label>
						<input type="number" min="0" max="43200" name="payout_hold_minutes" id="payout_hold_minutes"
							   class="form-control @error('payout_hold_minutes') is-invalid @enderror"
							   value="{{ old('payout_hold_minutes', $settings->payout_hold_minutes) }}">
						@error('payout_hold_minutes')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
					</div>

					<div class="col-md-6">
						<label for="min_order_amount" class="form-label">{{ __('Minimum order amount') }}</label>
						<input type="number" step="0.00000001" min="0" name="min_order_amount" id="min_order_amount"
							   class="form-control @error('min_order_amount') is-invalid @enderror"
							   value="{{ old('min_order_amount', $settings->min_order_amount) }}">
						@error('min_order_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
					</div>

					<div class="col-md-6">
						<label for="max_order_amount" class="form-label">{{ __('Maximum order amount') }}</label>
						<input type="number" step="0.00000001" min="0" name="max_order_amount" id="max_order_amount"
							   class="form-control @error('max_order_amount') is-invalid @enderror"
							   value="{{ old('max_order_amount', $settings->max_order_amount) }}">
						@error('max_order_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
						<div class="form-text">{{ __('Leave empty for no upper limit.') }}</div>
					</div>

					<div class="col-md-6">
						<label for="commission_user_id" class="form-label">{{ __('Commission account (User ID)') }}</label>
						<input type="number" min="1" name="commission_user_id" id="commission_user_id"
							   class="form-control @error('commission_user_id') is-invalid @enderror"
							   value="{{ old('commission_user_id', $settings->commission_user_id) }}"
							   placeholder="{{ __('e.g. 1') }}">
						@error('commission_user_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
						<div class="form-text">
							{{ __('The user whose wallet collects the commission.') }}
							@if($commissionUser)
								<span class="badge bg-success">{{ $commissionUser->name }} ({{ $commissionUser->email }})</span>
							@else
								<span class="text-danger">{{ __('Required before the marketplace can charge commission.') }}</span>
							@endif
						</div>
					</div>
				</div>

				<div class="mt-4">
					<button type="submit" class="btn btn-primary">
						<i class="fa-solid fa-floppy-disk me-1"></i> {{ __('Save Settings') }}
					</button>
				</div>
			</form>
		</div>
	</div>
@endsection
