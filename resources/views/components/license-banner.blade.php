@php
    $licenseBanner = app(\App\Support\LicenseGuard::class)->banner();

    if (! $licenseBanner) {
        return;
    }

    $tone = $licenseBanner['tone'] === 'danger' ? 'danger' : 'warning';
    $icon = $tone === 'danger' ? 'bi-shield-exclamation' : 'bi-exclamation-triangle-fill';
@endphp

<div class="alert alert-{{ $tone }} d-flex align-items-start gap-3 p-3 mb-4 shadow-sm" role="alert"
     style="border-left: 5px solid currentColor;">
    <i class="bi {{ $icon }} fs-4 lh-1 mt-1" aria-hidden="true"></i>
    <div class="flex-grow-1">
        <strong class="d-block mb-1">{{ $licenseBanner['title'] }}</strong>
        <div class="small mb-0">{{ $licenseBanner['message'] }}</div>
        @if(! empty($licenseBanner['action_url']))
            <a href="{{ $licenseBanner['action_url'] }}" class="btn btn-sm btn-{{ $tone }} mt-2">
                {{ $licenseBanner['action_label'] }}
            </a>
        @endif
    </div>
</div>
