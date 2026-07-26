@php use App\Enums\P2P\OrderSide;use App\Enums\P2P\OrderStatus; @endphp
@extends('frontend.layouts.user.index')
@section('title', __('Trade Order #').$order->id)
@section('content')
    {{-- Trade Order Details: participant state, order overview, action buttons, dispute handling, and review flow --}}
    @php
        $side = $order->offer->side; // enum App\Enums\P2P\OrderSide
        $actorId  = (int) auth()->id();
        $sellerId = (int) ($side === OrderSide::SELL ? $order->maker_id : $order->taker_id);
        $buyerId  = (int) ($side === OrderSide::SELL ? $order->taker_id : $order->maker_id);
        $role = $actorId === $sellerId ? 'seller' : ($actorId === $buyerId ? 'buyer' : 'guest');
        $isExpired = $order->status->value === 'PENDING' && $order->expires_at && now()->greaterThan($order->expires_at);

        $hasFeedback = $order->relationLoaded('feedbacks')
            ? $order->feedbacks->where('user_id', $actorId)->isNotEmpty()
            : false;
        $canRate = $order->status->value === 'COMPLETED' && $actorId === (int) $order->taker_id && ! $hasFeedback;
        $myFb = $hasFeedback ? $order->feedbacks->where('user_id', $actorId)->first() : null;
        $payerSnapshot = is_array($order->payer_payment_account_snapshot) ? $order->payer_payment_account_snapshot : [];
        $receiverSnapshot = is_array($order->receiver_payment_account_snapshot) ? $order->receiver_payment_account_snapshot : [];
        $payerDetails = collect($payerSnapshot['details'] ?? [])->filter(function ($detail) {
            return is_array($detail) && trim((string) ($detail['value'] ?? '')) !== '';
        })->values()->all();
        $receiverDetails = collect($receiverSnapshot['details'] ?? [])->filter(function ($detail) {
            return is_array($detail) && trim((string) ($detail['value'] ?? '')) !== '';
        })->values()->all();
        $selectedPaymentMethodName = $order->paymentMethod?->name ?? ($payerSnapshot['payment_method_name'] ?? $receiverSnapshot['payment_method_name'] ?? __('Not available'));

        $decimals       = (int) setting('site_decimal', 2);
        $assetCode      = (string) $order->wallet->currency->code;
        $fiatCode       = (string) $order->offer->quote_currency_code;
        $amountText     = rtrim(rtrim(number_format((float) $order->amount, 8, '.', ','), '0'), '.');
        $priceText      = number_format((float) $order->price, $decimals);
        $totalText      = number_format((float) $order->total, $decimals);
        $expiresText    = $isExpired ? __('Expired') : (optional($order->expires_at)->diffForHumans() ?? '—');
        $isParticipant  = in_array($actorId, [(int) $order->maker_id, (int) $order->taker_id], true);
        $canOpenDispute = in_array($order->status->value, ['PENDING', 'PAID'], true) && $isParticipant;
        // Money flow is always buyer pays fiat / receives crypto; seller is the mirror.
        $totalLabel  = $role === 'seller' ? __('You receive') : ($role === 'buyer' ? __('You pay') : __('Total'));
        $amountLabel = $role === 'seller' ? __('You sell') : ($role === 'buyer' ? __('You get') : __('Amount'));
    @endphp
    <div class="single-form-card p2p-ui p2p-trade-detail">
        <x-user-feature-header
            :title="__('Trade Order #').$order->id"
            :subtitle="__('Track participant actions, payment snapshots, and dispute status from one screen.')"
            icon="fas fa-file-alt"
        >
            <a href="{{ route('user.p2p.orders.index') }}" class="btn btn-light-primary btn-sm p2p-btn-xs">
                <i class="fas fa-arrow-left"></i> @lang('All Orders')
            </a>
        </x-user-feature-header>
        <div class="card-main p2p-card-main">

            {{-- Trade ticket: status badges + the four key numbers in one compact rail --}}
            <div class="p2p-trade-ticket">
                <div class="p2p-trade-ticket__head">
                    <div class="p2p-trade-ticket__badges">
                        <span id="p2pStatusBadge" class="{{ ($isExpired ? OrderStatus::EXPIRED : $order->status)->badgeClass() }}">{{ ($isExpired ? OrderStatus::EXPIRED : $order->status)->label() }}</span>
                        <span class="{{ $side->badgeClass() }}">{{ $side->label() }}</span>
                        @if($role !== 'guest')
                            <span class="p2p-trade-role-chip">
                                <i class="fas {{ $role === 'buyer' ? 'fa-shopping-cart' : 'fa-tag' }}" aria-hidden="true"></i>
                                {{ $role === 'buyer' ? __('You are the buyer') : __('You are the seller') }}
                            </span>
                        @endif
                    </div>
                    <span class="p2p-trade-expiry-chip {{ $isExpired ? 'is-expired' : '' }}">
                        <i class="far fa-clock" aria-hidden="true"></i>
                        <span>@lang('Expires'): <strong>{{ $expiresText }}</strong></span>
                    </span>
                </div>
                <div class="p2p-trade-ticket__stats">
                    <div class="p2p-trade-stat">
                        <span class="p2p-trade-stat__icon p2p-trade-stat__icon--primary"><i class="fas fa-money-bill-wave"></i></span>
                        <span class="p2p-trade-stat__copy">
                            <span class="p2p-trade-stat__label">{{ $totalLabel }}</span>
                            <span class="p2p-trade-stat__value">{{ $totalText }} <small>{{ $fiatCode }}</small></span>
                        </span>
                    </div>
                    <div class="p2p-trade-stat">
                        <span class="p2p-trade-stat__icon p2p-trade-stat__icon--info"><i class="fas fa-coins"></i></span>
                        <span class="p2p-trade-stat__copy">
                            <span class="p2p-trade-stat__label">{{ $amountLabel }}</span>
                            <span class="p2p-trade-stat__value">{{ $amountText }} <small>{{ $assetCode }}</small></span>
                        </span>
                    </div>
                    <div class="p2p-trade-stat">
                        <span class="p2p-trade-stat__icon p2p-trade-stat__icon--success"><i class="fas fa-chart-line"></i></span>
                        <span class="p2p-trade-stat__copy">
                            <span class="p2p-trade-stat__label">@lang('Price')</span>
                            <span class="p2p-trade-stat__value">{{ $priceText }} <small>{{ $fiatCode }}/{{ $assetCode }}</small></span>
                        </span>
                    </div>
                    <div class="p2p-trade-stat">
                        <span class="p2p-trade-stat__icon p2p-trade-stat__icon--warning"><i class="fas fa-hourglass-half"></i></span>
                        <span class="p2p-trade-stat__copy">
                            <span class="p2p-trade-stat__label">@lang('Window')</span>
                            <span class="p2p-trade-stat__value">{{ (int) ($order->offer->payment_window_minutes ?? 0) }} <small>@lang('min')</small></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                {{-- Payment details --}}
                <div class="col-lg-7">
                    <div class="p2p-trade-panel">
                        <div class="p2p-trade-panel__head">
                            <span class="p2p-trade-panel__icon"><i class="fas fa-building-columns"></i></span>
                            <div>
                                <h6 class="p2p-trade-panel__title">@lang('Payment Details')</h6>
                                <span class="p2p-trade-panel__hint">@lang('Method'): <strong>{{ $selectedPaymentMethodName }}</strong></span>
                            </div>
                        </div>
                        <div class="p2p-trade-panel__body">
                            @if($role === 'buyer' && in_array($order->status->value, ['PENDING'], true) && ! $isExpired)
                                <div class="p2p-trade-role-note">
                                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                                    <span>@lang('Send the exact amount to the receiver account below, then press "I have paid".')</span>
                                </div>
                            @elseif($role === 'seller' && in_array($order->status->value, ['PAID'], true))
                                <div class="p2p-trade-role-note">
                                    <i class="fas fa-circle-info" aria-hidden="true"></i>
                                    <span>@lang('Verify the payment arrived from the payer account below before releasing escrow.')</span>
                                </div>
                            @endif

                            <div class="p2p-snapshot-grid">
                                <div class="p2p-snapshot-card {{ $role === 'seller' ? 'p2p-snapshot-card--highlight' : '' }}">
                                    <div class="p2p-snapshot-card__head">
                                        <span class="p2p-snapshot-card__tag">@lang('Payer Account')</span>
                                        @if($role === 'seller')
                                            <span class="p2p-snapshot-card__focus"><i class="fas fa-arrow-down"></i> @lang('Payment comes from')</span>
                                        @endif
                                    </div>
                                    @if(!empty($payerSnapshot))
                                        <div class="p2p-snapshot-row"><span class="p2p-snapshot-row__label">@lang('Method')</span><span class="p2p-snapshot-row__value">{{ $payerSnapshot['payment_method_name'] ?? '-' }}</span></div>
                                        <div class="p2p-snapshot-row"><span class="p2p-snapshot-row__label">@lang('Account')</span><span class="p2p-snapshot-row__value">{{ $payerSnapshot['account_label'] ?? $payerSnapshot['display_name'] ?? '-' }}</span></div>
                                        @foreach($payerDetails as $detail)
                                            <div class="p2p-snapshot-row"><span class="p2p-snapshot-row__label">{{ $detail['label'] ?? '-' }}</span><span class="p2p-snapshot-row__value">{{ $detail['value'] ?? '-' }}</span></div>
                                        @endforeach
                                        @if(!empty($payerSnapshot['method_instructions']))
                                            <div class="p2p-snapshot-card__note">{!! nl2br(e($payerSnapshot['method_instructions'])) !!}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">@lang('No payer account snapshot available.')</span>
                                    @endif
                                </div>
                                <div class="p2p-snapshot-card {{ $role === 'buyer' ? 'p2p-snapshot-card--highlight' : '' }}">
                                    <div class="p2p-snapshot-card__head">
                                        <span class="p2p-snapshot-card__tag">@lang('Receiver Account')</span>
                                        @if($role === 'buyer')
                                            <span class="p2p-snapshot-card__focus"><i class="fas fa-paper-plane"></i> @lang('Send payment here')</span>
                                        @endif
                                    </div>
                                    @if(!empty($receiverSnapshot))
                                        <div class="p2p-snapshot-row"><span class="p2p-snapshot-row__label">@lang('Method')</span><span class="p2p-snapshot-row__value">{{ $receiverSnapshot['payment_method_name'] ?? '-' }}</span></div>
                                        <div class="p2p-snapshot-row"><span class="p2p-snapshot-row__label">@lang('Account')</span><span class="p2p-snapshot-row__value">{{ $receiverSnapshot['account_label'] ?? $receiverSnapshot['display_name'] ?? '-' }}</span></div>
                                        @foreach($receiverDetails as $detail)
                                            <div class="p2p-snapshot-row"><span class="p2p-snapshot-row__label">{{ $detail['label'] ?? '-' }}</span><span class="p2p-snapshot-row__value">{{ $detail['value'] ?? '-' }}</span></div>
                                        @endforeach
                                        @if(!empty($receiverSnapshot['method_instructions']))
                                            <div class="p2p-snapshot-card__note">{!! nl2br(e($receiverSnapshot['method_instructions'])) !!}</div>
                                        @endif
                                    @else
                                        <span class="text-muted small">@lang('No receiver account snapshot available.')</span>
                                    @endif
                                </div>
                            </div>

                            <div class="p2p-trade-subsection">
                                <span class="p2p-trade-subsection__label">@lang('Offer Payment Methods')</span>
                                <div class="p2p-payment-pills">
                                    @forelse($order->offer->paymentMethods as $pm)
                                        <span class="p2p-payment-pill" @if(!empty($pm->instructions)) tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-container="body" data-bs-content="{{ $pm->instructions }}" @endif>
                                            <span class="p2p-payment-pill__text">{{ $pm->name }}</span>
                                        </span>
                                    @empty
                                        <span class="text-muted">@lang('No payment methods')</span>
                                    @endforelse
                                </div>
                            </div>

                            @if(!empty($order->offer->terms_text))
                                <div class="p2p-trade-subsection">
                                    <span class="p2p-trade-subsection__label">@lang('Terms')</span>
                                    <div class="p2p-trade-terms">{!! nl2br(e($order->offer->terms_text)) !!}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Actions + dispute --}}
                <div class="col-lg-5">
                    <div class="p2p-trade-panel">
                        <div class="p2p-trade-panel__head">
                            <span class="p2p-trade-panel__icon"><i class="fas fa-bolt"></i></span>
                            <div>
                                <h6 class="p2p-trade-panel__title">@lang('Order Actions')</h6>
                                <span class="p2p-trade-panel__hint">@lang('Only the steps available to you are shown.')</span>
                            </div>
                        </div>
                        <div class="p2p-trade-panel__body">
                            <div class="p2p-trade-actions">
                                <div id="p2pActionPaid">
                                    @if($order->status->value === 'PENDING' && !$isExpired && $actorId === $buyerId)
                                        <form method="POST" action="{{ route('user.p2p.orders.paid', $order) }}">
                                            @csrf
                                            <button class="btn btn-base w-100 p2p-trade-action-btn" type="submit">
                                                <i class="fas fa-check-circle me-1"></i> @lang('I have paid')
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <div id="p2pActionRelease">
                                    @if($order->status->value === 'PAID' && $actorId === $sellerId)
                                        <form method="POST" action="{{ route('user.p2p.orders.release', $order) }}">
                                            @csrf
                                            <button class="btn btn-base w-100 p2p-trade-action-btn" type="submit">
                                                <i class="fas fa-unlock-alt me-1"></i> @lang('Release Escrow')
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <div id="p2pActionCancel">
                                    @if(in_array($order->status->value, ['PENDING']) && !$isExpired && $isParticipant)
                                        <form method="POST" action="{{ route('user.p2p.orders.cancel', $order) }}" onsubmit="return confirm(@json(__('Are you sure to cancel?')))">
                                            @csrf
                                            <button class="btn btn-light-primary w-100 p2p-trade-action-btn" type="submit">
                                                <i class="fas fa-times-circle me-1"></i> @lang('Cancel')
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                @if($canRate)
                                    <button type="button" class="btn btn-light-warning w-100 p2p-trade-action-btn" data-bs-toggle="modal" data-bs-target="#p2pFeedbackModal">
                                        <i class="fas fa-star me-1"></i> @lang('Review')
                                    </button>
                                @elseif($order->status->value === 'COMPLETED' && $myFb)
                                    <div class="p2p-trade-rated">
                                        <span class="p2p-trade-rated__label">@lang('Your review')</span>
                                        <span class="p2p-trade-rated__stars" aria-label="{{ (int) $myFb->rating }}/5">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="fas fa-star {{ $i <= (int) $myFb->rating ? 'is-filled' : '' }}" aria-hidden="true"></i>
                                            @endfor
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="p2p-trade-escrow-note">
                                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                                @lang('Funds held in escrow until payment is confirmed')
                            </div>

                            @if($canOpenDispute)
                                <div class="p2p-trade-dispute">
                                    <button class="p2p-trade-dispute__toggle collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#p2pDisputeCollapse" aria-expanded="false" aria-controls="p2pDisputeCollapse">
                                        <span><i class="fas fa-gavel me-2" aria-hidden="true"></i>@lang('Having a problem with this trade?')</span>
                                        <i class="fas fa-chevron-down p2p-trade-dispute__chevron" aria-hidden="true"></i>
                                    </button>
                                    <div class="collapse" id="p2pDisputeCollapse">
                                        <div class="p2p-trade-dispute__body">
                                            <p class="p2p-trade-dispute__hint">@lang('Open a dispute only if the counterparty is unresponsive or the payment cannot be resolved directly. Our team will review the evidence.')</p>
                                            <form method="POST" action="{{ route('user.p2p.orders.dispute', $order) }}">
                                                @csrf
                                                <label class="form-label" for="p2pDisputeReason">@lang('Reason')</label>
                                                <textarea name="reason" id="p2pDisputeReason" class="form-control" rows="3" required placeholder="@lang('Describe what happened, with dates and references.')"></textarea>
                                                <button class="btn btn-light-danger btn-sm p2p-btn-xs mt-2" type="submit">
                                                    <i class="fas fa-gavel me-1"></i> @lang('Open Trade Dispute')
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($isParticipant)
                        @include('frontend.user.p2p.trade_orders.partials._trade_order_chat')
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Info modal (Binance-style) shown once on creation for buyer/seller --}}
    <div class="modal fade p2p-ui-modal p2p-account-modal" id="p2pInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header p2p-account-modal__header">
                    <div class="p2p-account-modal__heading">
                        <span class="p2p-account-modal__icon" aria-hidden="true"><i class="fas fa-lightbulb"></i></span>
                        <div class="p2p-account-modal__heading-text">
                            <h5 class="modal-title p2p-account-modal__title">@lang('Important Information')</h5>
                            <p class="p2p-account-modal__subtitle">{{ $role === 'seller' ? __('Follow these steps to settle this trade safely.') : __('Follow these steps to complete your payment safely.') }}</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('Close')"></button>
                </div>
                <div class="modal-body p2p-account-modal__body">
                    @if($role === 'buyer')
                        <ol class="p2p-info-steps">
                            <li>@lang('Pay the seller using the listed payment method(s).')</li>
                            <li>@lang('After you transfer the funds, click "I have paid" before the order expires.')</li>
                            <li>@lang('Do not cancel if you have already paid. Contact the seller in case of issues.')</li>
                        </ol>
                    @elseif($role === 'seller')
                        <ol class="p2p-info-steps">
                            <li>@lang('Wait for the buyer to mark as paid and verify the payment in your account.')</li>
                            <li>@lang('Release escrow only after confirming payment receipt.')</li>
                            <li>@lang('If payment is not received before expiry, you may cancel to refund escrow.')</li>
                        </ol>
                    @else
                        <p class="mb-0">@lang('You are viewing this order as a guest.')</p>
                    @endif
                </div>
                <div class="modal-footer p2p-account-modal__footer">
                    <span class="p2p-account-modal__footer-note">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                        @lang('Funds held in escrow until payment is confirmed')
                    </span>
                    <div class="p2p-account-modal__footer-actions">
                        <button type="button" class="btn btn-base" data-bs-dismiss="modal">
                            <i class="fas fa-check me-1"></i> @lang('Got it')
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Review Modal --}}
    <div class="modal fade p2p-ui-modal" id="p2pFeedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Review Trade') <span class="text-muted">#{{ $order->id }}</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('user.p2p.orders.feedback', $order) }}" id="p2pFeedbackForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">@lang('Rating')</label>
                            <input type="hidden" name="rating" id="p2pFeedbackRating" value="{{ old('rating', 5) }}">
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
                            <textarea name="comment" class="form-control " rows="3" maxlength="500">{{ old('comment') }}</textarea>
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
@endsection
{{-- Trade Order Script: local state persistence, onboarding modal, status polling, and review modal helpers --}}
@push('scripts')
    @include('frontend.user.p2p.trade_orders.partials._trade_order_details_scripts')
    @if($isParticipant)
        @include('frontend.user.p2p.trade_orders.partials._trade_order_chat_scripts')
    @endif
@endpush
