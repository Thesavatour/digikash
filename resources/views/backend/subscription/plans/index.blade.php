@extends('backend.subscription.layout')

@section('title', __('Subscription Plans'))
@section('sub_title', __('Subscription Plans'))
@section('sub_icon', 'apps')
@section('sub_subtitle', __('Create and manage plans, pricing, billing cycles, and feature limits.'))

@section('sub_action')
	@can('subscription-manage')
		<a href="{{ route('admin.subscription.plans.create') }}" class="btn btn-primary d-flex align-items-center gap-2">
			<x-icon name="add" height="20" width="20"/>
			@lang('Add Plan')
		</a>
	@endcan
@endsection

@section('sub_content')

	<div class="admin-users-page">
		{{-- Stats --}}
		<div class="admin-users-stats" aria-label="{{ __('Subscription plan summary') }}">
			<div class="admin-users-stat">
				<span class="admin-users-stat__icon admin-users-stat__icon--primary">
					<x-icon name="apps" height="18" width="18"/>
				</span>
				<div>
					<span class="admin-users-stat__label">@lang('Total Plans')</span>
					<span class="admin-users-stat__value">{{ $stats['total_plans'] }}</span>
				</div>
			</div>
			<div class="admin-users-stat">
				<span class="admin-users-stat__icon admin-users-stat__icon--success">
					<x-icon name="check" height="18" width="18"/>
				</span>
				<div>
					<span class="admin-users-stat__label">@lang('Active Plans')</span>
					<span class="admin-users-stat__value">{{ $stats['active_plans'] }}</span>
				</div>
			</div>
			<div class="admin-users-stat">
				<span class="admin-users-stat__icon admin-users-stat__icon--info">
					<x-icon name="people" height="18" width="18"/>
				</span>
				<div>
					<span class="admin-users-stat__label">@lang('Active Subscribers')</span>
					<span class="admin-users-stat__value">{{ number_format($stats['total_subscribers']) }}</span>
				</div>
			</div>
			<div class="admin-users-stat">
				<span class="admin-users-stat__icon admin-users-stat__icon--warning">
					<x-icon name="money" height="18" width="18"/>
				</span>
				<div>
					<span class="admin-users-stat__label">@lang('Total Revenue')</span>
					<span class="admin-users-stat__value">{{ siteCurrency() }} {{ number_format($stats['total_revenue'], 2) }}</span>
				</div>
			</div>
		</div>

		<section class="admin-users-panel">
			<div class="admin-users-filterbar">
				<form method="GET" action="{{ route('admin.subscription.plans.index') }}" class="admin-users-filters row g-2 g-xl-3 align-items-end">
					<div class="admin-users-filter col-12 col-md-5 col-xl-3">
						<label class="form-label fw-semibold" for="billing-cycle-filter">@lang('Billing Cycle')</label>
						<select id="billing-cycle-filter" name="billing_cycle" class="form-select" onchange="this.form.submit()">
							<option value="">@lang('All Cycles')</option>
							@foreach(\App\Enums\BillingCycle::options() as $value => $label)
								<option value="{{ $value }}" @selected($cycleFilter === $value)>{{ $label }}</option>
							@endforeach
						</select>
					</div>
					@if($cycleFilter)
						<div class="admin-users-filter col-12 col-md-auto">
							<a href="{{ route('admin.subscription.plans.index') }}" class="admin-users-search__reset">
								<i class="fa-solid fa-xmark" aria-hidden="true"></i>
								@lang('Clear Filter')
							</a>
						</div>
					@endif
				</form>
			</div>

			<div class="admin-users-table-wrap">
				@if($plans->isNotEmpty())
					<table class="table admin-users-table admin-users-table--plans mb-0">
						<thead>
						<tr>
							<th>@lang('Plan')</th>
							<th>@lang('Pricing')</th>
							<th>@lang('Features')</th>
							<th>@lang('Subscribers')</th>
							<th>@lang('Status')</th>
							@can('subscription-manage')
								<th class="text-end">@lang('Actions')</th>
							@endcan
						</tr>
						</thead>
						<tbody>
						@foreach($plans as $plan)
							<tr class="admin-users-row align-middle">
								<td>
									<div class="admin-users-doc admin-plan-doc">
										<span class="admin-users-name admin-plan-name">{{ $plan->name }}</span>
										@if($plan->is_featured || $plan->plan_badge)
											<div class="admin-users-doc__badges">
												@if($plan->is_featured)
													<span class="admin-users-pill admin-users-pill--warning">@lang('Featured')</span>
												@endif
												@if($plan->plan_badge)
													<span class="admin-users-pill admin-users-pill--primary">{{ $plan->plan_badge }}</span>
												@endif
											</div>
										@endif
										@if($plan->trial_days > 0)
											<span class="admin-plan-trial-pill">
												<i class="fa-solid fa-gift" aria-hidden="true"></i>
												{{ trans_choice(':count day trial|:count days trial', $plan->trial_days, ['count' => $plan->trial_days]) }}
											</span>
										@endif
									</div>
								</td>
								<td>
									@if($plan->prices->isEmpty())
										<span class="admin-users-meta">@lang('No pricing set')</span>
									@else
										<div class="admin-plan-prices">
											@foreach($plan->prices as $price)
												<div class="admin-plan-price">
													<span class="admin-plan-price__cycle">{{ $price->billing_cycle->label() }}</span>
													@if($price->isFree())
														<strong class="admin-plan-price__amount admin-plan-price__amount--free">@lang('Free')</strong>
													@else
														<strong class="admin-plan-price__amount">{{ siteCurrency('code') }} {{ number_format($price->price, 2) }}</strong>
													@endif
												</div>
											@endforeach
										</div>
									@endif
								</td>
								<td>
									<span class="admin-plan-count">{{ $plan->features->count() }}</span>
									<span class="admin-users-meta">@lang('features')</span>
								</td>
								<td>
									<div class="admin-plan-subscribers">
										<strong>{{ number_format($plan->active_subscriptions_count) }}</strong>
										<span>{{ number_format($plan->subscriptions_count) }} @lang('total')</span>
									</div>
								</td>
								<td>
									<span class="admin-users-pill {{ $plan->status ? 'admin-users-pill--success' : 'admin-users-pill--danger' }}">
										{{ $plan->status ? __('Active') : __('Disabled') }}
									</span>
								</td>
								@can('subscription-manage')
									<td class="text-end">
										<div class="admin-users-actions">
											<a href="{{ route('admin.subscription.plans.edit', $plan) }}" class="admin-users-action">
												<x-icon name="manage" height="14" width="14"/>
												@lang('Edit')
											</a>
											<form method="POST" action="{{ route('admin.subscription.plans.toggle-status', $plan) }}">
												@csrf
												<button type="submit" class="admin-users-action {{ $plan->status ? 'admin-users-action--warning' : 'admin-users-action--success' }}">
													<x-icon name="{{ $plan->status ? 'close' : 'check' }}" height="14" width="14"/>
													{{ $plan->status ? __('Disable') : __('Enable') }}
												</button>
											</form>
											{{--
												Project-wide delete handler (public/general/js/helpers.js):
												a click on `.delete` reads `data-url`, points the global
												#delete-form-modal at it and pops the styled #delete_modal
												confirmation — the same UX as Gift Card Templates, replacing
												the native browser confirm() dialog.
											--}}
											<a href="javascript:void(0)"
											   data-url="{{ route('admin.subscription.plans.destroy', $plan) }}"
											   class="admin-users-action admin-users-action--danger delete">
												<x-icon name="delete-3" height="14" width="14"/>
												@lang('Delete')
											</a>
										</div>
									</td>
								@endcan
							</tr>
						@endforeach
						</tbody>
					</table>
				@else
					<div class="admin-users-empty">
						@can('subscription-manage')
							<x-admin-not-found
								:title="__('No subscription plans yet')"
								:message="__('Create a subscription plan to start selling recurring access.')"
								icon="fa-list"
								:action-url="route('admin.subscription.plans.create')"
								:action-label="__('Create your first plan')"
							/>
						@else
							<x-admin-not-found
								:title="__('No subscription plans yet')"
								:message="__('Subscription plans will appear here once they are created.')"
								icon="fa-list"
							/>
						@endcan
					</div>
				@endif
			</div>
		</section>
	</div>

@endsection
