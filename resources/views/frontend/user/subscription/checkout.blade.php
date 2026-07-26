@extends('frontend.layouts.user.index')

@section('title', __('Checkout - :plan', ['plan' => $plan->name]))

@push('styles')
    <link rel="stylesheet" href="{{ asset('frontend/css/subscription-checkout.css?v=' . config('app.version')) }}">
@endpush

@section('content')
@php
    use App\Enums\BillingCycle;

    $isUpgrade = $isSwitch && (int) $plan->sort_order > (int) $current->plan->sort_order;
    $isDowngrade = $isSwitch && (int) $plan->sort_order < (int) $current->plan->sort_order;

    $headingTitle = match (true) {
        $isUpgrade   => __('Upgrade to :plan', ['plan' => $plan->name]),
        $isDowngrade => __('Switch to :plan', ['plan' => $plan->name]),
        $isSwitch    => __('Change billing cycle'),
        default      => __('Subscribe to :plan', ['plan' => $plan->name]),
    };

    $headingSub = match (true) {
        $isUpgrade   => __('Unlock more features. We will only charge the prorated difference.'),
        $isDowngrade => __('Switch to a smaller plan. Unused time on your current plan is credited.'),
        $isSwitch    => __('Adjust your billing cycle. Unused time is applied as credit.'),
        default      => __('Confirm your details and pay from your wallet to activate.'),
    };

    $planIcon = match (true) {
        $plan->isFree()    => 'fas fa-seedling',
        $plan->is_featured => 'fas fa-crown',
        default            => 'fas fa-rocket',
    };

    $selected = $cyclesData[$billingCycle->value];
    $proration = $selected['proration'];
    $fullPrice = (float) $proration['new_plan_price'];
    $credit = (float) $proration['credit'];
    $charge = (float) $proration['charge'];
    $remaining = (int) $proration['remaining_days'];
    $isFree = $fullPrice <= 0;
    $isTrialCheckout = ! $isSwitch && ! $isFree && $plan->trial_days > 0 && $charge <= 0;
    $insufficient = $charge > 0 && $walletBalance < $charge;
    $perMonth = match ($billingCycle) {
        BillingCycle::HalfYearly => $fullPrice > 0 ? round($fullPrice / 6, 2) : 0,
        BillingCycle::Yearly => $fullPrice > 0 ? round($fullPrice / 12, 2) : 0,
        default => $fullPrice,
    };

    $visibleFeatures = $plan->features->take(6);
    $hiddenFeatures = $plan->features->slice($visibleFeatures->count())->values();
    $hiddenFeatureCount = $hiddenFeatures->count();
@endphp

<div class="row">
    <div class="col-12">
        <div class="card single-form-card subscription-checkout-card">
            <x-user-feature-header
                :title="$headingTitle"
                :subtitle="$headingSub"
                :icon="$planIcon"
                compact
            >
                <a href="{{ route('user.subscription.plans') }}" class="btn btn-light-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Plans') }}
                </a>
                <span class="ck-secure-chip">
                    <i class="fa-solid fa-lock"></i>
                    {{ __('Secure checkout') }}
                </span>
            </x-user-feature-header>

            <div class="card-body ck-body">
                <div class="ck-grid">
                    <section class="ck-plan-card">
                        <div class="ck-plan-card__header">
                            <div class="ck-plan-card__icon">
                                <i class="{{ $planIcon }}"></i>
                            </div>
                            <div class="ck-plan-card__copy">
                                <div class="ck-plan-card__eyebrow">{{ __('Selected plan') }}</div>
                                <div class="ck-plan-card__title-row">
                                    <h3 class="ck-plan-card__name">{{ $plan->name }}</h3>
                                    @if($plan->plan_badge)
                                        <span class="ck-plan-card__badge">
                                            <i class="fa-regular fa-star"></i>
                                            {{ $plan->plan_badge }}
                                        </span>
                                    @endif
                                </div>
                                <p class="ck-plan-card__tagline">{{ $plan->description ?: __('Premium plan with full feature access.') }}</p>
                            </div>

                            @if($plan->trial_days > 0 || $plan->grace_days > 0)
                                <div class="ck-perks">
                                    @if($plan->trial_days > 0)
                                        <span class="ck-perk">
                                            <i class="fa-regular fa-calendar-check"></i>
                                            {{ $plan->trial_days }}-{{ __('day free trial') }}
                                        </span>
                                    @endif
                                    @if($plan->grace_days > 0)
                                        <span class="ck-perk">
                                            <i class="fa-solid fa-shield-alt"></i>
                                            {{ $plan->grace_days }}-{{ __('day grace period') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if($visibleFeatures->isNotEmpty())
                            <div class="ck-features">
                                <div class="ck-features__heading">{{ __('Key inclusions') }}</div>
                                <ul class="ck-features__list">
                                    @foreach($visibleFeatures as $feature)
                                        @php
                                            $isLockedFeature = $feature->isAccess() && ! $feature->isEnabled();
                                        @endphp
                                        <li class="ck-feature {{ $isLockedFeature ? 'is-muted' : '' }}">
                                            <span class="ck-feature__check">
                                                <i class="fa-solid {{ $isLockedFeature ? 'fa-lock' : 'fa-check' }}"></i>
                                            </span>
                                            <span class="ck-feature__label">{{ $feature->feature_label }}</span>
                                            <span class="ck-feature__value {{ $isLockedFeature ? 'ck-feature__value--muted' : '' }}">{{ $feature->displayValue() }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($hiddenFeatureCount > 0)
                                    <details class="ck-hidden-benefits" open>
                                        <summary class="ck-feature-more">
                                            <i class="fa-solid fa-circle-plus"></i>
                                            {{ __(':count more benefits included', ['count' => $hiddenFeatureCount]) }}
                                        </summary>
                                        <div class="ck-hidden-benefits__list">
                                            @foreach($hiddenFeatures as $feature)
                                                @php
                                                    $isLockedFeature = $feature->isAccess() && ! $feature->isEnabled();
                                                @endphp
                                                <div class="ck-hidden-benefits__item {{ $isLockedFeature ? 'is-muted' : '' }}">
                                                    <span class="ck-hidden-benefits__label">{{ $feature->feature_label }}</span>
                                                    <span class="ck-hidden-benefits__value {{ $isLockedFeature ? 'ck-feature__value--muted' : '' }}">{{ $feature->displayValue() }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endif

                        @if($isSwitch)
                            <div class="ck-from-block">
                                <div class="ck-from-block__lbl">{{ __('Switching from') }}</div>
                                <div class="ck-from-block__val">
                                    <strong>{{ $current->plan->name }}</strong>
                                    @if($current->billing_cycle)
                                        <span>- {{ $current->billing_cycle->label() }}</span>
                                    @endif
                                </div>
                                @if($current->current_period_end)
                                    <div class="ck-from-block__sub">
                                        {{ __('Current period ends :date - unused time becomes credit', ['date' => $current->current_period_end->format('d M Y')]) }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </section>

                    <aside class="ck-order-card">
                        <div class="ck-order-card__head">
                            <h3 class="ck-order-card__title">{{ __('Order summary') }}</h3>
                            <span>{{ $selected['label'] }}</span>
                        </div>

                        <div class="ck-cycle-selector" id="ckCycleSelector">
                            @foreach($cyclesData as $cycleValue => $cycleData)
                                @php
                                    $isActive = $cycleValue === $billingCycle->value;
                                @endphp
                                <a href="{{ route('user.subscription.checkout', ['plan' => $plan->slug, 'cycle' => $cycleValue]) }}"
                                   class="ck-cycle-btn {{ $isActive ? 'is-active' : '' }}">
                                    <span class="ck-cycle-btn__lbl">{{ $cycleData['label'] }}</span>
                                    @if($cycleData['discount'])
                                        <span class="ck-cycle-btn__save">-{{ $cycleData['discount'] }}%</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>

                        <div class="ck-breakdown">
                            <div class="ck-breakdown__row">
                                <span>{{ $plan->name }}</span>
                                <b>{{ $currencyCode }} {{ number_format($fullPrice, 2) }}</b>
                            </div>
                            @if($billingCycle->value !== 'monthly' && $perMonth > 0)
                                <div class="ck-breakdown__row ck-breakdown__row--meta">
                                    <span>{{ __('Equivalent') }}</span>
                                    <small>{{ $currencyCode }} {{ number_format($perMonth, 2) }}/{{ __('mo') }}</small>
                                </div>
                            @endif

                            @if($credit > 0)
                                <div class="ck-breakdown__row ck-breakdown__row--credit">
                                    <span>
                                        {{ __('Prorated credit') }}
                                        <small>{{ trans_choice(':n day remaining|:n days remaining', $remaining, ['n' => $remaining]) }}</small>
                                    </span>
                                    <b>- {{ $currencyCode }} {{ number_format($credit, 2) }}</b>
                                </div>
                            @endif

                            @if($isTrialCheckout)
                                <div class="ck-breakdown__row ck-breakdown__row--credit">
                                    <span>
                                        {{ __('Free trial') }}
                                        <small>{{ trans_choice(':count day included|:count days included', $plan->trial_days, ['count' => $plan->trial_days]) }}</small>
                                    </span>
                                    <b>- {{ $currencyCode }} {{ number_format($fullPrice, 2) }}</b>
                                </div>
                            @endif

                            <div class="ck-breakdown__divider"></div>

                            <div class="ck-breakdown__row ck-breakdown__row--total">
                                <span>{{ __('Due today') }}</span>
                                <b>{{ $currencyCode }} {{ number_format($charge, 2) }}</b>
                            </div>
                        </div>

                        <div class="ck-wallet {{ $insufficient ? 'ck-wallet--low' : '' }}">
                            <div class="ck-wallet__icon"><i class="fa-solid fa-wallet"></i></div>
                            <div class="ck-wallet__main">
                                <div class="ck-wallet__lbl">{{ __('Wallet balance') }}</div>
                                <div class="ck-wallet__val">{{ $currencyCode }} {{ number_format($walletBalance, 2) }}</div>
                            </div>
                            @if($insufficient)
                                <div class="ck-wallet__warn">
                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                    {{ __('Top up :amount more to continue', ['amount' => $currencyCode.' '.number_format($charge - $walletBalance, 2)]) }}
                                </div>
                            @elseif($isTrialCheckout)
                                <div class="ck-wallet__ok">
                                    <i class="fa-solid fa-gift"></i>
                                    {{ __('No charge today') }}
                                </div>
                            @else
                                <div class="ck-wallet__ok">
                                    <i class="fa-solid fa-check-circle"></i>
                                    {{ __('Ready for checkout') }}
                                </div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('user.subscription.subscribe') }}" class="ck-pay-form">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="billing_cycle" value="{{ $billingCycle->value }}">
                            <button type="submit" class="ck-pay-btn" @disabled($insufficient)>
                                @if($isFree)
                                    <i class="fa-solid fa-bolt"></i> {{ __('Activate Free Plan') }}
                                @elseif($isTrialCheckout)
                                    <i class="fa-solid fa-gift"></i> {{ __('Start Free Trial') }}
                                @elseif($isSwitch)
                                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                                    {{ __('Confirm switch') }} - {{ $currencyCode }} {{ number_format($charge, 2) }}
                                @else
                                    <i class="fa-solid fa-lock"></i>
                                    {{ __('Pay & subscribe') }} - {{ $currencyCode }} {{ number_format($charge, 2) }}
                                @endif
                            </button>
                            <a href="{{ route('user.subscription.plans') }}" class="ck-cancel-link">{{ __('Cancel') }}</a>
                        </form>

                        <div class="ck-trust">
                            <span>
                                <i class="fa-solid fa-shield-halved"></i>
                                <b>{{ __('Encrypted') }}</b>
                                <small>{{ __('Bank-level security') }}</small>
                            </span>
                            <span>
                                <i class="fa-solid fa-rotate"></i>
                                <b>{{ __('Cancel anytime') }}</b>
                                <small>{{ __('No commitments') }}</small>
                            </span>
                            <span>
                                <i class="fa-solid fa-receipt"></i>
                                <b>{{ __('Wallet receipt') }}</b>
                                <small>{{ __('Email confirmation') }}</small>
                            </span>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
