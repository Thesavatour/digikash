@php
    $providerId = $provider?->id ?? 'new';
    $driver = old('driver', $provider?->driver ?? 'local');
@endphp

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <label class="form-label" for="providerName{{ $providerId }}">{{ __('Name') }}</label>
        <input id="providerName{{ $providerId }}" name="name" value="{{ old('name', $provider?->name) }}" class="form-control" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="providerSlug{{ $providerId }}">{{ __('Slug') }}</label>
        <input id="providerSlug{{ $providerId }}" name="slug" value="{{ old('slug', $provider?->slug) }}" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="providerDriver{{ $providerId }}">{{ __('Driver') }}</label>
        <select id="providerDriver{{ $providerId }}" name="driver" class="form-select" data-media-provider-driver>
            <option value="local" @selected($driver === 'local')>{{ __('Local') }}</option>
            <option value="s3" @selected($driver === 's3')>{{ __('S3 Compatible') }}</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="providerVisibility{{ $providerId }}">{{ __('Visibility') }}</label>
        <select id="providerVisibility{{ $providerId }}" name="visibility" class="form-select">
            <option value="public" @selected(old('visibility', $provider?->visibility ?? 'public') === 'public')>{{ __('Public') }}</option>
            <option value="private" @selected(old('visibility', $provider?->visibility) === 'private')>{{ __('Private') }}</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="providerPrefix{{ $providerId }}">{{ __('Path Prefix') }}</label>
        <input id="providerPrefix{{ $providerId }}" name="prefix" value="{{ old('prefix', $provider?->prefix ?? 'media') }}" class="form-control" required>
    </div>
    <div class="col-md-4" data-local-fields>
        <label class="form-label" for="providerRoot{{ $providerId }}">{{ __('Local Root') }}</label>
        <input id="providerRoot{{ $providerId }}" name="root" value="{{ old('root', $provider?->root) }}" class="form-control" placeholder="{{ storage_path('app/public') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="providerUrl{{ $providerId }}">{{ __('Public/CDN URL') }}</label>
        <input id="providerUrl{{ $providerId }}" name="url" value="{{ old('url', $provider?->url) }}" class="form-control" placeholder="{{ asset('storage') }}">
    </div>
</div>

<div class="row g-3 mb-3" data-s3-fields>
    <div class="col-md-4">
        <label class="form-label" for="providerBucket{{ $providerId }}">{{ __('Bucket') }}</label>
        <input id="providerBucket{{ $providerId }}" name="bucket" value="{{ old('bucket', $provider?->bucket) }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="providerRegion{{ $providerId }}">{{ __('Region') }}</label>
        <input id="providerRegion{{ $providerId }}" name="region" value="{{ old('region', $provider?->region) }}" class="form-control" placeholder="us-east-1">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="providerEndpoint{{ $providerId }}">{{ __('Endpoint') }}</label>
        <input id="providerEndpoint{{ $providerId }}" name="endpoint" value="{{ old('endpoint', $provider?->endpoint) }}" class="form-control" placeholder="https://s3.amazonaws.com">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="providerAccessKey{{ $providerId }}">{{ __('Access Key') }}</label>
        <input id="providerAccessKey{{ $providerId }}" name="access_key" class="form-control" autocomplete="off" @if(! $provider) required @endif>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="providerSecretKey{{ $providerId }}">{{ __('Secret Key') }}</label>
        <input id="providerSecretKey{{ $providerId }}" name="secret_key" type="password" class="form-control" autocomplete="new-password" @if(! $provider) required @endif>
        @if($provider?->isS3Compatible())
            <div class="form-text">{{ __('Leave blank to keep the saved secret.') }}</div>
        @endif
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="providerPathStyle{{ $providerId }}" name="use_path_style_endpoint" value="1" @checked(old('use_path_style_endpoint', $provider?->use_path_style_endpoint))>
            <label class="form-check-label" for="providerPathStyle{{ $providerId }}">{{ __('Use path-style endpoint') }}</label>
        </div>
    </div>
</div>

<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" id="providerActive{{ $providerId }}" name="is_active" value="1" @checked(old('is_active', $provider?->is_active))>
    <label class="form-check-label" for="providerActive{{ $providerId }}">{{ __('Make active for new uploads') }}</label>
</div>
