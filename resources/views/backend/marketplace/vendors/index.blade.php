@extends('backend.layouts.app')
@section('title', __('Marketplace Vendors'))
@section('content')
	<div class="py-4">
		<h1 class="h4 mb-3">{{ __('Marketplace Vendors') }}</h1>
	</div>

	<div class="card border-0 mb-4">
		<div class="card-body">
			<form action="{{ route('admin.marketplace.vendors.index') }}" method="GET" class="d-flex flex-wrap gap-2 mb-3 justify-content-end">
				<div>
					<label for="status" class="form-label small">{{ __('Status') }}</label>
					<select name="status" class="form-select">
						<option value="">{{ __('All') }}</option>
						<option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
						<option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
						<option value="suspended" @selected(request('status') === 'suspended')>{{ __('Suspended') }}</option>
					</select>
				</div>
				<div>
					<label for="search" class="form-label small">{{ __('Search') }}</label>
					<div class="input-group">
						<input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Vendor or email...') }}">
						<button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
					</div>
				</div>
			</form>

			<div class="table-responsive">
				<table class="table border mb-0">
					<thead class="table-light fw-semibold">
					<tr class="align-middle text-nowrap">
						<th>{{ __('Vendor') }}</th>
						<th>{{ __('User') }}</th>
						<th>{{ __('Status') }}</th>
						<th>{{ __('Joined') }}</th>
						@can('marketplace-vendor-manage')
							<th>{{ __('Actions') }}</th>
						@endcan
					</tr>
					</thead>
					<tbody>
					@forelse($vendors as $vendor)
						<tr class="align-middle">
							<td>{{ $vendor->display_name }}</td>
							<td>
								<div>{{ $vendor->user?->name }}</div>
								<div class="small text-muted">{{ $vendor->user?->email }}</div>
							</td>
							<td><span class="{{ $vendor->status->badgeClass() }}">{{ $vendor->status->label() }}</span></td>
							<td>
								<div>{{ $vendor->created_at?->format('Y-m-d') }}</div>
								<div class="small text-muted">{{ $vendor->created_at?->diffForHumans() }}</div>
							</td>
							@can('marketplace-vendor-manage')
								<td>
									<div class="d-flex gap-2">
										@if($vendor->status !== \App\Enums\Marketplace\VendorStatus::ACTIVE)
											<form action="{{ route('admin.marketplace.vendors.approve', $vendor) }}" method="POST">
												@csrf
												<button type="submit" class="btn btn-sm btn-success text-white">
													<i class="fa-solid fa-check me-1"></i> {{ __('Approve') }}
												</button>
											</form>
										@endif
										@if($vendor->status !== \App\Enums\Marketplace\VendorStatus::SUSPENDED)
											<form action="{{ route('admin.marketplace.vendors.suspend', $vendor) }}" method="POST">
												@csrf
												<button type="submit" class="btn btn-sm btn-danger text-white">
													<i class="fa-solid fa-ban me-1"></i> {{ __('Suspend') }}
												</button>
											</form>
										@endif
									</div>
								</td>
							@endcan
						</tr>
					@empty
						<tr>
							<td colspan="{{ auth()->user()?->can('marketplace-vendor-manage') ? 5 : 4 }}">
								<x-admin-not-found
									:title="__('No vendors found')"
									:message="__('Vendor applications will appear here once users apply.')"
									icon="fa-store"
								/>
							</td>
						</tr>
					@endforelse
					</tbody>
				</table>
			</div>

			<div class="d-flex justify-content-end mt-3">
				{{ $vendors->links() }}
			</div>
		</div>
	</div>
@endsection
