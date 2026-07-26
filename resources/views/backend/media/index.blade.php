@extends('backend.layouts.app')

@section('title', __('Media Library'))

@push('scripts')
	@php
		$mediaLibraryJsVersion = config('app.version') . '-' . filemtime(public_path('backend/js/media-library.js'));
	@endphp
	<script src="{{ asset('backend/js/media-library.js?v=' . $mediaLibraryJsVersion) }}"></script>
@endpush

@section('content')
	@php
		$activeTab = request('tab') === 'storage' ? 'storage' : 'library';
		$assetTypes = [
			'image' => __('Images'),
			'document' => __('Documents'),
			'video' => __('Videos'),
			'audio' => __('Audio'),
			'archive' => __('Archives'),
			'other' => __('Other'),
		];
	@endphp
	
	<div class="mla-admin py-4">
		<div class="mla-header mb-3">
			<div>
				<h1 class="mla-title mb-1">
					<span class="mla-title__icon"><i class="fas fa-photo-video"></i></span>
					<span>{{ __('Media Library') }}</span>
				</h1>
				<p class="text-muted mb-0">{{ __('Upload reusable assets and route new files to local or S3-compatible storage.') }}</p>
			</div>
			
			<div class="mla-header__actions">
				<a href="{{ route('admin.media.index', ['tab' => 'storage']) }}" class="btn btn-light">
					<i class="fas fa-database me-1"></i>{{ __('Storage') }}
				</a>
				@can('media-upload')
					<button class="btn btn-primary" data-coreui-toggle="modal" data-coreui-target="#mediaUploadModal">
						<i class="fas fa-upload" aria-hidden="true"></i>{{ __('Upload Media') }}
					</button>
				@endcan
			</div>
		</div>
		
		<ul class="nav nav-pills mla-tabs mb-3">
			<li class="nav-item">
				<a @class(['nav-link', 'active' => $activeTab === 'library']) href="{{ route('admin.media.index') }}">
					<i class="fas fa-th-large me-1"></i>{{ __('Library') }}
				</a>
			</li>
			<li class="nav-item">
				<a @class(['nav-link', 'active' => $activeTab === 'storage']) href="{{ route('admin.media.index', ['tab' => 'storage']) }}">
					<i class="fas fa-server me-1"></i>{{ __('Storage Providers') }}
				</a>
			</li>
		</ul>
		
		@if($activeTab === 'library')
			<div class="mla-kpi-grid mb-3">
				<div class="mla-kpi">
					<span class="mla-kpi__icon mla-kpi__icon--primary"><i class="fas fa-folder-open"></i></span>
					<div>
						<span>{{ __('Total Assets') }}</span>
						<strong>{{ $metrics['total'] }}</strong>
					</div>
				</div>
				<div class="mla-kpi">
					<span class="mla-kpi__icon mla-kpi__icon--success"><i class="fas fa-image"></i></span>
					<div>
						<span>{{ __('Images') }}</span>
						<strong>{{ $metrics['images'] }}</strong>
					</div>
				</div>
				<div class="mla-kpi">
					<span class="mla-kpi__icon mla-kpi__icon--warning"><i class="fas fa-file-alt"></i></span>
					<div>
						<span>{{ __('Documents') }}</span>
						<strong>{{ $metrics['documents'] }}</strong>
					</div>
				</div>
				<div class="mla-kpi">
					<span class="mla-kpi__icon mla-kpi__icon--info"><i class="fas fa-database"></i></span>
					<div>
						<span>{{ __('Active Storage') }}</span>
						<strong>{{ $activeProvider->name }}</strong>
					</div>
				</div>
			</div>
			
			<div class="card border-0 mla-filter mb-3">
				<div class="card-body">
					<form method="GET" action="{{ route('admin.media.index') }}" class="row g-2 align-items-end">
						<div class="col-md-4">
							<label for="mediaSearch" class="form-label">{{ __('Search') }}</label>
							<input id="mediaSearch" type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Name, title, alt text') }}">
						</div>
						<div class="col-md-2">
							<label for="mediaType" class="form-label">{{ __('Type') }}</label>
							<select id="mediaType" name="type" class="form-select">
								<option value="">{{ __('All Types') }}</option>
								@foreach($assetTypes as $value => $label)
									<option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3">
							<label for="mediaProvider" class="form-label">{{ __('Provider') }}</label>
							<select id="mediaProvider" name="provider" class="form-select">
								<option value="">{{ __('All Providers') }}</option>
								@foreach($providers as $provider)
									<option value="{{ $provider->id }}" @selected((string) request('provider') === (string) $provider->id)>{{ $provider->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-2">
							<label for="mediaFolder" class="form-label">{{ __('Folder') }}</label>
							<select id="mediaFolder" name="folder" class="form-select">
								<option value="">{{ __('All Folders') }}</option>
								@foreach($folders as $folder)
									<option value="{{ $folder }}" @selected(request('folder') === $folder)>{{ $folder }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-1 d-grid">
							<button class="btn btn-primary" type="submit" aria-label="{{ __('Filter') }}">
								<i class="fas fa-filter"></i>
							</button>
						</div>
					</form>
				</div>
			</div>
			
			@if($assets->isEmpty())
				<div class="card border-0 mla-empty-card">
					<div class="card-body">
						<x-admin-not-found
							:title="__('No media files found')"
							:message="__('Upload images, documents, or brand assets to reuse across pages, blogs, and settings.')"
							icon="fa-photo-video"
							:action-url="auth('admin')->user()?->can('media-upload') ? '#' : null"
							:action-label="auth('admin')->user()?->can('media-upload') ? __('Upload Media') : null"
							action-icon="fa-upload"
							class="mla-empty-upload"
						/>
					</div>
				</div>
			@else
				<div class="mla-grid mb-3">
					@foreach($assets as $asset)
						<article class="mla-card">
							<div class="mla-card__preview">
								@if($asset->isImage())
									<img src="{{ $asset->url }}" alt="{{ $asset->alt_text ?: $asset->displayTitle() }}" loading="lazy">
								@else
									<span class="mla-file-icon">
                                        <i @class([
                                            'fas',
                                            'fa-file-alt' => $asset->aggregate_type === 'document',
                                            'fa-file-archive' => $asset->aggregate_type === 'archive',
                                            'fa-file-video' => $asset->aggregate_type === 'video',
                                            'fa-file-audio' => $asset->aggregate_type === 'audio',
                                            'fa-file' => ! in_array($asset->aggregate_type, ['document', 'archive', 'video', 'audio'], true),
                                        ])></i>
                                    </span>
								@endif
							</div>
							<div class="mla-card__body">
								<div class="mla-card__title" title="{{ $asset->displayTitle() }}">{{ $asset->displayTitle() }}</div>
								<div class="mla-card__meta">
									<span>{{ strtoupper((string) $asset->extension) }}</span>
									<span>{{ $asset->humanSize() }}</span>
									<span>{{ $asset->storageProvider?->name ?? __('Unknown') }}</span>
								</div>
								<div class="mla-card__actions">
									<button type="button" class="btn btn-light btn-sm" data-media-copy="{{ $asset->url }}" title="{{ __('Copy URL') }}">
										<i class="fas fa-copy"></i>
									</button>
									<a href="{{ $asset->url }}" target="_blank" rel="noopener" class="btn btn-light btn-sm" title="{{ __('Open') }}">
										<i class="fas fa-external-link-alt"></i>
									</a>
									@can('media-edit')
										<button type="button" class="btn btn-light btn-sm" data-coreui-toggle="modal" data-coreui-target="#mediaEditModal{{ $asset->id }}" title="{{ __('Edit') }}">
											<i class="fas fa-pencil-alt"></i>
										</button>
									@endcan
									@can('media-delete')
										<button type="button" class="btn btn-danger btn-sm delete" data-url="{{ route('admin.media.destroy', $asset) }}" title="{{ __('Delete') }}">
											<i class="fas fa-trash-alt"></i>
										</button>
									@endcan
								</div>
							</div>
						</article>
						
						@can('media-edit')
							<div class="modal fade" id="mediaEditModal{{ $asset->id }}" tabindex="-1" aria-labelledby="mediaEditModal{{ $asset->id }}Label" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered">
									<div class="modal-content">
										<form method="POST" action="{{ route('admin.media.update', $asset) }}">
											@csrf
											@method('PUT')
											<div class="modal-header">
												<h2 class="modal-title fs-5" id="mediaEditModal{{ $asset->id }}Label">{{ __('Edit Media Details') }}</h2>
												<button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="{{ __('Close') }}"></button>
											</div>
											<div class="modal-body">
												<div class="mb-3">
													<label class="form-label" for="mediaTitle{{ $asset->id }}">{{ __('Title') }}</label>
													<input id="mediaTitle{{ $asset->id }}" name="title" value="{{ old('title', $asset->title) }}" class="form-control">
												</div>
												<div class="mb-3">
													<label class="form-label" for="mediaAlt{{ $asset->id }}">{{ __('Alt Text') }}</label>
													<input id="mediaAlt{{ $asset->id }}" name="alt_text" value="{{ old('alt_text', $asset->alt_text) }}" class="form-control">
												</div>
												<div class="mb-3">
													<label class="form-label" for="mediaFolder{{ $asset->id }}">{{ __('Folder') }}</label>
													<input id="mediaFolder{{ $asset->id }}" name="folder" value="{{ old('folder', $asset->folder) }}" class="form-control">
												</div>
												<div>
													<label class="form-label" for="mediaCaption{{ $asset->id }}">{{ __('Caption') }}</label>
													<textarea id="mediaCaption{{ $asset->id }}" name="caption" rows="3" class="form-control">{{ old('caption', $asset->caption) }}</textarea>
												</div>
											</div>
											<div class="modal-footer">
												<button type="button" class="btn btn-light" data-coreui-dismiss="modal">{{ __('Cancel') }}</button>
												<button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
											</div>
										</form>
									</div>
								</div>
							</div>
						@endcan
					@endforeach
				</div>
				
				{{ $assets->links() }}
			@endif
		@else
			<div class="row g-3">
				<div class="col-xl-4">
					<div class="card border-0 mla-storage-card h-100">
						<div class="card-body">
							<div class="mla-storage-active">
								<span class="mla-storage-active__icon"><i class="fas fa-database"></i></span>
								<div>
									<span>{{ __('Active Provider') }}</span>
									<strong>{{ $activeProvider->name }}</strong>
									<small>{{ strtoupper($activeProvider->driver) }} / {{ strtoupper($activeProvider->visibility) }}</small>
								</div>
							</div>
							<p class="text-muted mt-3 mb-0">{{ __('New uploads use the active provider. Existing media keeps loading from the provider that originally stored it.') }}</p>
						</div>
					</div>
				</div>
				
				@can('media-storage-manage')
					<div class="col-xl-8">
						<div class="card border-0 mla-storage-card">
							<div class="card-body">
								<h2 class="h5 mb-3">{{ __('Add Storage Provider') }}</h2>
								<form method="POST" action="{{ route('admin.media.storage-providers.store') }}" data-media-provider-form>
									@csrf
									@include('backend.media.partials._provider_form', ['provider' => null])
									<div class="d-flex justify-content-end">
										<button type="submit" class="btn btn-primary">
											<i class="fas fa-plus me-1"></i>{{ __('Create Provider') }}
										</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				@endcan
			</div>
			
			<div class="card border-0 mt-3">
				<div class="card-body">
					<div class="table-responsive">
						<table class="table align-middle">
							<thead>
							<tr>
								<th>{{ __('Provider') }}</th>
								<th>{{ __('Driver') }}</th>
								<th>{{ __('Prefix') }}</th>
								<th>{{ __('Files') }}</th>
								<th>{{ __('Last Test') }}</th>
								<th>{{ __('Action') }}</th>
							</tr>
							</thead>
							<tbody>
							@forelse($providers as $provider)
								<tr>
									<td>
										<div class="fw-semibold">{{ $provider->name }}</div>
										<div class="text-muted small">{{ $provider->slug }}</div>
									</td>
									<td>
                                        <span @class(['badge', 'bg-success' => $provider->is_active, 'bg-secondary' => ! $provider->is_active])>
                                            {{ strtoupper($provider->driver) }}
                                        </span>
									</td>
									<td>{{ $provider->resolvedPrefix() }}</td>
									<td>{{ $provider->assets_count }}</td>
									<td>
										@if($provider->last_checked_at)
											<span @class(['badge', 'bg-success' => $provider->last_check_status === 'success', 'bg-danger' => $provider->last_check_status === 'failed'])>
                                                {{ $provider->last_check_status }}
                                            </span>
											<div class="text-muted small">{{ $provider->last_checked_at->diffForHumans() }}</div>
										@else
											<span class="text-muted">{{ __('Not tested') }}</span>
										@endif
									</td>
									<td>
										<div class="btn-group btn-group-sm">
											@can('media-storage-manage')
												@if(! $provider->is_active)
													<form method="POST" action="{{ route('admin.media.storage-providers.activate', $provider) }}">
														@csrf
														<button class="btn btn-light" type="submit">{{ __('Activate') }}</button>
													</form>
												@endif
												<form method="POST" action="{{ route('admin.media.storage-providers.test', $provider) }}">
													@csrf
													<button class="btn btn-light" type="submit">{{ __('Test') }}</button>
												</form>
												<button class="btn btn-light" type="button" data-coreui-toggle="collapse" data-coreui-target="#providerEdit{{ $provider->id }}">
													{{ __('Edit') }}
												</button>
												@if(! $provider->is_active && $provider->assets_count === 0)
													<button type="button" class="btn btn-danger delete" data-url="{{ route('admin.media.storage-providers.destroy', $provider) }}">
														{{ __('Delete') }}
													</button>
												@endif
											@endcan
										</div>
									</td>
								</tr>
								@can('media-storage-manage')
									<tr class="collapse" id="providerEdit{{ $provider->id }}">
										<td colspan="6">
											<form method="POST" action="{{ route('admin.media.storage-providers.update', $provider) }}" data-media-provider-form>
												@csrf
												@method('PUT')
												@include('backend.media.partials._provider_form', ['provider' => $provider])
												<div class="d-flex justify-content-end">
													<button type="submit" class="btn btn-primary">{{ __('Save Provider') }}</button>
												</div>
											</form>
										</td>
									</tr>
								@endcan
							@empty
								<tr>
									<td colspan="6">
										<x-admin-not-found
											:title="__('No storage providers found')"
											:message="__('A local provider will be created automatically the first time the media library opens.')"
											icon="fa-database"
										/>
									</td>
								</tr>
							@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		@endif
	</div>
	
	@can('media-upload')
		<div class="modal fade" id="mediaUploadModal" tabindex="-1" aria-labelledby="mediaUploadModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-centered">
				<div class="modal-content">
					<form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data">
						@csrf
						<div class="modal-header">
							<h2 class="modal-title fs-5" id="mediaUploadModalLabel">{{ __('Upload Media') }}</h2>
							<button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="{{ __('Close') }}"></button>
						</div>
						<div class="modal-body">
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label" for="mediaUploadFiles">{{ __('Files') }}</label>
									<input id="mediaUploadFiles" type="file" name="files[]" class="form-control" multiple required>
									<div class="form-text">{{ __('Images, documents, archives, audio, and video are supported.') }}</div>
								</div>
								<div class="col-md-6">
									<label class="form-label" for="mediaUploadProvider">{{ __('Storage Provider') }}</label>
									<select id="mediaUploadProvider" name="storage_provider_id" class="form-select">
										@foreach($providers as $provider)
											<option value="{{ $provider->id }}" @selected($provider->id === $activeProvider->id)>{{ $provider->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label" for="mediaUploadFolder">{{ __('Folder') }}</label>
									<input id="mediaUploadFolder" name="folder" class="form-control" placeholder="{{ __('campaigns/home') }}">
								</div>
								<div class="col-md-6">
									<label class="form-label" for="mediaUploadTitle">{{ __('Title') }}</label>
									<input id="mediaUploadTitle" name="title" class="form-control">
								</div>
								<div class="col-md-6">
									<label class="form-label" for="mediaUploadAlt">{{ __('Alt Text') }}</label>
									<input id="mediaUploadAlt" name="alt_text" class="form-control">
								</div>
								<div class="col-md-6">
									<label class="form-label" for="mediaUploadCaption">{{ __('Caption') }}</label>
									<textarea id="mediaUploadCaption" name="caption" rows="2" class="form-control"></textarea>
								</div>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-light" data-coreui-dismiss="modal">{{ __('Cancel') }}</button>
							<button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	@endcan
@endsection
