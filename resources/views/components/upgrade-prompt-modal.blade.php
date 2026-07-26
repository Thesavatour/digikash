@props([
    'id'              => 'upgradePromptModal',
    'plansUrl'        => null,
    'defaultFeature'  => null,
    'defaultMessage'  => null,
])

@php
    $plansUrl       = $plansUrl ?: route('user.subscription.plans');
    $defaultFeature = $defaultFeature ?: __('Premium Feature');
    $defaultMessage = $defaultMessage ?: __('This action is not included in your current plan. Upgrade to unlock the full experience.');
@endphp

<div class="modal fade upm" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content upm__content">
            <button type="button" class="btn-close upm__close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>

            <div class="upm__hero">
                <div class="upm__icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h5 class="upm__title" id="{{ $id }}Title" data-upgrade-feature-label>
                    {{ $defaultFeature }}
                </h5>
                <p class="upm__copy" data-upgrade-message>
                    {{ $defaultMessage }}
                </p>
            </div>

            <div class="upm__body">
                <div class="upm__benefits-title">{{ __('Unlock with Pro or Business') }}</div>
                <ul class="upm__benefits">
                    <li><i class="fas fa-check-circle"></i> {{ __('Higher daily and monthly transaction limits') }}</li>
                    <li><i class="fas fa-check-circle"></i> {{ __('P2P trading - Buy, Sell, and Create ads') }}</li>
                    <li><i class="fas fa-check-circle"></i> {{ __('Larger wallet balance cap and virtual card slots') }}</li>
                    <li><i class="fas fa-check-circle"></i> {{ __('Wallet earning and staking access') }}</li>
                    <li><i class="fas fa-check-circle"></i> {{ __('Faster withdrawal handling') }}</li>
                    <li><i class="fas fa-check-circle"></i> {{ __('Priority customer support') }}</li>
                </ul>
            </div>

            <div class="upm__footer">
                <button type="button" class="btn btn-light upm__btn-secondary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-clock"></i> {{ __('Maybe Later') }}
                </button>
                <a href="{{ $plansUrl }}" class="btn theme-btn upm__btn-primary">
                    <i class="fa-solid fa-arrow-right"></i> {{ __('View Plans') }}
                </a>
            </div>
        </div>
    </div>
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('frontend/css/upgrade-prompt-modal.css?v=' . config('app.version') . '-' . filemtime(public_path('frontend/css/upgrade-prompt-modal.css'))) }}">
    @endpush
@endonce
