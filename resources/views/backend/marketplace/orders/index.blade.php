@extends('backend.layouts.app')
@section('title', __('Marketplace Orders'))
@section('content')
	<div class="py-4">
		<h1 class="h4 mb-3">{{ __('Marketplace Orders') }}</h1>
	</div>

	<div class="card border-0 mb-4">
		<div class="card-body">
			<form action="{{ route('admin.marketplace.orders.index') }}" method="GET" class="d-flex flex-wrap gap-2 mb-3 justify-content-end">
				<div>
					<label for="status" class="form-label small">{{ __('Status') }}</label>
					<select name="status" class="form-select">
						<option value="">{{ __('All') }}</option>
						<option value="released" @selected(request('status') === 'released')>{{ __('Released') }}</option>
						<option value="refunded" @selected(request('status') === 'refunded')>{{ __('Refunded') }}</option>
						<option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
						<option value="cancelled" @selected(request('status') === 'cancelled')>{{ __('Cancelled') }}</option>
					</select>
				</div>
				<div class="align-self-end">
					<button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>{{ __('Filter') }}</button>
				</div>
			</form>

			<div class="table-responsive">
				<table class="table border mb-0">
					<thead class="table-light fw-semibold">
					<tr class="align-middle text-nowrap">
						<th>#</th>
						<th>{{ __('Buyer') }}</th>
						<th>{{ __('Vendor') }}</th>
						<th>{{ __('Gross') }}</th>
						<th>{{ __('Commission') }}</th>
						<th>{{ __('Vendor net') }}</th>
						<th>{{ __('Status') }}</th>
						<th>{{ __('Date') }}</th>
						@can('marketplace-order-manage')
							<th>{{ __('Actions') }}</th>
						@endcan
					</tr>
					</thead>
					<tbody>
					@forelse($orders as $order)
						<tr class="align-middle">
							<td>{{ $order->id }}</td>
							<td>
								<div>{{ $order->buyer?->name }}</div>
								<div class="small text-muted">{{ $order->buyer?->email }}</div>
							</td>
							<td>{{ $order->vendor?->display_name }}</td>
							<td>{{ number_format((float) $order->gross_amount, 2) }} {{ $order->currency?->code }}</td>
							<td>{{ number_format((float) $order->commission_amount, 2) }}</td>
							<td>{{ number_format((float) $order->vendor_net, 2) }}</td>
							<td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
							<td>
								<div>{{ $order->created_at?->format('Y-m-d') }}</div>
								<div class="small text-muted">{{ $order->created_at?->diffForHumans() }}</div>
							</td>
							@can('marketplace-order-manage')
								<td>
									@if($order->status === \App\Enums\Marketplace\OrderStatus::RELEASED)
										<form action="{{ route('admin.marketplace.orders.refund', $order) }}" method="POST"
											  onsubmit="return confirm('{{ __('Refund this order back to the buyer?') }}');">
											@csrf
											<button type="submit" class="btn btn-sm btn-warning">
												<i class="fa-solid fa-rotate-left me-1"></i> {{ __('Refund') }}
											</button>
										</form>
									@else
										<span class="text-muted small">&mdash;</span>
									@endif
								</td>
							@endcan
						</tr>
					@empty
						<tr>
							<td colspan="{{ auth()->user()?->can('marketplace-order-manage') ? 9 : 8 }}">
								<x-admin-not-found
									:title="__('No orders yet')"
									:message="__('Marketplace orders will appear here as buyers purchase from vendors.')"
									icon="fa-bag-shopping"
								/>
							</td>
						</tr>
					@endforelse
					</tbody>
				</table>
			</div>

			<div class="d-flex justify-content-end mt-3">
				{{ $orders->links() }}
			</div>
		</div>
	</div>
@endsection
