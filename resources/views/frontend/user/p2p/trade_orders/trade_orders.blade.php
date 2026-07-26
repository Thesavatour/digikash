@extends('frontend.layouts.user.index')
@section('title', __('My Trade Orders'))
@section('content')
{{-- Trade Orders List: active/history cards, resume banner, and quick review modal launcher --}}
@php
    $totalOrders = method_exists($orders, 'total') ? (int) $orders->total() : (int) $orders->count();
    $visibleOrders = (int) $orders->count();
@endphp
<div class="single-form-card p2p-ui p2p-orders-page">
    <x-user-feature-header
        :title="__('My Trade Orders')"
        :subtitle="__('Review open activity, resume recent trades, and jump into order details quickly.')"
        icon="fas fa-exchange-alt"
    >
        <a href="{{ route('user.p2p.offers.index') }}" class="btn btn-light-primary btn-sm p2p-btn-xs">
            <i class="fas fa-store"></i> @lang('Marketplace')
        </a>
    </x-user-feature-header>
    <div class="card-main p2p-card-main">
        {{-- Resume shortcut for the last active trade order --}}
        <div id="p2pResumeBanner" class="p2p-resume-banner d-none" role="status" aria-live="polite">
            <div class="p2p-resume-left">
                <span class="p2p-resume-icon-wrap">
                    <i class="fas fa-clock-rotate-left p2p-resume-icon"></i>
                </span>
                <div class="p2p-resume-content">
                    <div class="p2p-resume-title-row">
                        <span class="p2p-resume-title">@lang('Resume last trade')</span>
                        <span id="p2pResumeStatus" class="p2p-resume-status d-none"></span>
                    </div>
                    <div class="p2p-resume-order-row">
                        <strong id="p2pResumeOrderText">@lang('Order') #</strong>
                        <span id="p2pResumeUpdated" class="p2p-resume-updated d-none"></span>
                    </div>
                    <div id="p2pResumeMeta" class="p2p-resume-meta"></div>
                </div>
            </div>
            <a id="p2pResumeLink" href="#" class="btn btn-light-primary btn-sm p2p-btn-xs p2p-resume-link">
                <i class="fas fa-external-link-alt me-1"></i> @lang('Open Trade')
                <i class="fas fa-chevron-right ms-1"></i>
            </a>
        </div>

        <div class="p2p-offers-panel p2p-orders-panel">
            <div class="p2p-offers-panel__head p2p-orders-panel__head">
                <div class="p2p-orders-panel__title-group">
                    <span class="p2p-orders-panel__eyebrow">@lang('P2P order desk')</span>
                    <h6 class="p2p-offers-panel__title">@lang('Trade Orders')</h6>
                </div>
                <div class="p2p-orders-panel__summary" aria-label="@lang('Order list summary')">
                    <span><i class="fas fa-layer-group" aria-hidden="true"></i>{{ trans_choice(':count trade|:count trades', $totalOrders, ['count' => number_format($totalOrders)]) }}</span>
                    <span><i class="fas fa-tasks" aria-hidden="true"></i>{{ trans_choice(':count visible|:count visible', $visibleOrders, ['count' => number_format($visibleOrders)]) }}</span>
                    <span><i class="far fa-clock" aria-hidden="true"></i>@lang('Latest first')</span>
                </div>
            </div>
            {{-- Trade order cards --}}
            <div class="p2p-offers-panel__body p2p-orders-list">
                @forelse($orders as $o)
                    @php
                        $currency = $o->wallet->currency->code;
                        $decimals = (int) setting('site_decimal', 2);
                        $actorId = (int) auth()->id();
                        $side = $o->offer->side;
                        $sideValue = strtolower($side->value);
                        $statusValue = strtolower($o->status->value);
                        $quoteCurrency = $o->offer->quote_currency_code;
                        $amountText = rtrim(rtrim(number_format((float) $o->amount, 8, '.', ','), '0'), '.');
                        $priceText = number_format((float) $o->price, $decimals);
                        $orderTotal = $o->total !== null ? (float) $o->total : ((float) $o->amount * (float) $o->price);
                        $totalText = number_format($orderTotal, $decimals);
                        $paymentMethodName = $o->paymentMethod?->name;
                        $hasFeedback = $o->relationLoaded('feedbacks')
                            ? $o->feedbacks->where('user_id', $actorId)->isNotEmpty()
                            : false;
                        $canRate = $o->status->value === 'COMPLETED' && $actorId === (int) $o->taker_id && ! $hasFeedback;
                    @endphp
                    <div class="p2p-data-card p2p-order-row p2p-order-row--{{ $sideValue }} p2p-order-row--status-{{ $statusValue }}">
                        <span class="p2p-order-row__accent" aria-hidden="true"></span>
                        <div class="p2p-data-card__left p2p-order-row__main">
                            <span class="p2p-order-row__icon p2p-order-row__icon--{{ $sideValue }}" aria-hidden="true">
                                <i class="fas {{ $sideValue === 'sell' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                            </span>
                            <div class="p2p-order-row__copy">
                                <div class="p2p-order-row__top">
                                    <div class="p2p-order-row__identity">
                                        <span class="p2p-order-row__eyebrow">@lang('Order') #{{ $o->id }}</span>
                                        <div class="p2p-data-card__title p2p-order-row__title">
                                            <span>{{ $side->label() }} {{ $currency }}</span>
                                            <span class="{{ $side->badgeClass() }} p2p-order-row__badge">{{ $side->label() }}</span>
                                            <span class="{{ $o->status->badgeClass() }} p2p-order-row__badge">{{ $o->status->label() }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="p2p-order-row__metrics" aria-label="@lang('Order amounts')">
                                    <div class="p2p-order-row__metric p2p-order-row__metric--total">
                                        <span>@lang('Total')</span>
                                        <strong>{{ $totalText }} <small>{{ $quoteCurrency }}</small></strong>
                                    </div>
                                    <div class="p2p-order-row__metric">
                                        <span>@lang('Amount')</span>
                                        <strong>{{ $amountText }} <small>{{ $currency }}</small></strong>
                                    </div>
                                    <div class="p2p-order-row__metric">
                                        <span>@lang('Rate')</span>
                                        <strong>{{ $priceText }} <small>{{ $quoteCurrency }}/{{ $currency }}</small></strong>
                                    </div>
                                </div>

                                <div class="p2p-data-card__sub p2p-order-row__meta">
                                    <span class="p2p-order-meta-chip"><i class="far fa-clock" aria-hidden="true"></i>{{ optional($o->created_at)->diffForHumans() }}</span>
                                    @if($paymentMethodName)
                                        <span class="p2p-order-meta-chip"><i class="fas fa-university" aria-hidden="true"></i>{{ $paymentMethodName }}</span>
                                    @endif
                                    <span class="p2p-order-meta-chip"><i class="fas fa-fingerprint" aria-hidden="true"></i>{{ $o->trx_id ?: __('Trade') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="p2p-data-card__right p2p-order-row__actions">
                            <a class="btn btn-light-primary btn-sm p2p-btn-xs p2p-order-row__view" href="{{ route('user.p2p.orders.show', $o) }}">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                <span>@lang('View')</span>
                                <i class="fas fa-chevron-right p2p-order-row__view-arrow" aria-hidden="true"></i>
                            </a>

                            @if($canRate)
                                <button type="button"
                                        class="btn btn-light-warning btn-sm p2p-btn-xs p2p-rate-order-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#p2pFeedbackModal"
                                        data-feedback-url="{{ route('user.p2p.orders.feedback', $o) }}"
                                        data-order="#{{ $o->id }}"
                                >
                                    <i class="fas fa-star" aria-hidden="true"></i> @lang('Review')
                                </button>
                            @elseif($o->status->value === 'COMPLETED' && $hasFeedback)
                                @php
                                    $myFb = $o->feedbacks->where('user_id', $actorId)->first();
                                @endphp
                                @if($myFb)
                                    <span class="p2p-order-rating-badge"><i class="fas fa-star" aria-hidden="true"></i>{{ (int) $myFb->rating }}/5</span>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <x-user-not-found
                        :title="__('No trade orders yet')"
                        :message="__('Trade orders will appear here after you start buying or selling from the marketplace.')"
                        :eyebrow="__('P2P order desk')"
                        icon="fa-exchange-alt"
                        :action-url="route('user.p2p.offers.index')"
                        :action-label="__('Marketplace')"
                        action-icon="fa-store"
                    />
                @endforelse
            </div>
        </div>

        <div class="modal fade p2p-ui-modal" id="p2pFeedbackModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Review Trade') <span class="text-muted" id="p2pFeedbackOrderLabel"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="p2pFeedbackForm">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">@lang('Rating')</label>
                                <input type="hidden" name="rating" id="p2pFeedbackRating" value="5">
                                <div class="d-flex align-items-center gap-2" id="p2pFeedbackStars">
                                    @for($i = 1; $i <= 5; $i++)
                                        <button type="button" class="btn btn-link p-0 p2p-feedback-star" data-value="{{ $i }}" aria-label="@lang('Review') {{ $i }}">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    @endfor
                                </div>
                                @error('rating')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-0">
                                <label class="form-label">@lang('Comment') (@lang('optional'))</label>
                                <textarea name="comment" class="form-control form-control-sm" rows="3" maxlength="500">{{ old('comment') }}</textarea>
                                @error('comment')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">@lang('Cancel')</button>
                            <button type="submit" class="btn btn-warning btn-sm">@lang('Submit')</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{ $orders->links() }}
    </div>
</div>

@push('scripts')
    @include('frontend.user.p2p.trade_orders.partials._trade_orders_scripts')
@endpush
@endsection
