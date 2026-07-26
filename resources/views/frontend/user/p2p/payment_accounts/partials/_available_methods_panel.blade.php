<div class="p2p-offers-panel p2p-accounts-side">
    <div class="p2p-offers-panel__head">
        <div class="p2p-accounts-panel__lead">
            <div class="p2p-accounts-panel__title-row">
                <span class="p2p-accounts-panel__title-icon p2p-accounts-panel__title-icon--spark"><i class="fas fa-layer-group"></i></span>
                <div class="p2p-accounts-panel__title-copy">
                    <h6 class="p2p-offers-panel__title mb-0">@lang('Methods Still Available')</h6>
                    <p class="p2p-accounts-panel__subtitle">@lang('Add more methods to keep your setup flexible.')</p>
                </div>
            </div>
        </div>
    </div>

    <div class="p2p-offers-panel__body p2p-accounts-methods">
        @forelse($availableMethodCards as $methodCard)
            <a href="{{ $methodCard['create_url'] }}" class="p2p-accounts-method">
                <span class="p2p-accounts-method__identity">
                    <span class="p2p-account-card__logo-shell">
                        @if($methodCard['logo_url'])
                            <img src="{{ $methodCard['logo_url'] }}" alt="{{ $methodCard['name'] }}" class="p2p-method-logo" loading="lazy">
                        @else
                            <span class="p2p-method-fallback">{{ $methodCard['initial'] }}</span>
                        @endif
                    </span>
                    <span class="p2p-accounts-method__copy">
                        <span class="p2p-account-label">{{ $methodCard['name'] }}</span>
                        <span class="p2p-account-meta">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            {{ $methodCard['country_label'] }}
                        </span>
                    </span>
                </span>
                <span class="p2p-accounts-method__cta">
                    <span>@lang('Add')</span>
                    <span class="p2p-accounts-method__arrow" aria-hidden="true">
                        <i class="fas fa-arrow-right"></i>
                    </span>
                </span>
            </a>
        @empty
            <div class="p2p-accounts-complete">
                <div class="p2p-empty-icon"><i class="fas fa-circle-check"></i></div>
                <h6 class="fw-semibold mb-2">@lang('Coverage complete')</h6>
                <p class="small text-muted mb-0">@lang('You already have an account saved for every method currently available to you.')</p>
            </div>
        @endforelse
    </div>

    @if($queuedMethodsCount > 0)
        <div class="p2p-accounts-side__notice">
            <span class="p2p-accounts-side__notice-badge">+{{ $formattedQueuedMethodsCount }}</span>
            <div class="p2p-accounts-side__notice-content">
                <span class="p2p-accounts-side__notice-label">@lang('Up next')</span>
                <span class="p2p-accounts-side__notice-text">{{ $queuedMethodsSummary }}</span>
            </div>
            <span class="p2p-accounts-side__notice-icon" aria-hidden="true">
                <i class="fas fa-layer-group"></i>
            </span>
        </div>
    @endif
</div>
