@extends('frontend.layouts.user.index')
@section('title', __('Trader').': '.$user->name)
@section('content')
	@use('Illuminate\Support\Str')
	
	@php
		$userName = $user->name ?? __('User');
		$initials = strtoupper(substr((string) $user?->first_name, 0, 1).substr((string) $user?->last_name, 0, 1));
		$initials = $initials !== '' ? $initials : strtoupper(substr((string) $userName, 0, 1));
		$isVerified = $user ? $user->isKycVerified() : false;
		$avgRatingValue = $avgRating !== null ? number_format((float) $avgRating, 1) : null;
		$activeAdsCount = method_exists($offers, 'total') ? (int) $offers->total() : (int) $offers->count();
		$positiveRate = $feedbackCount > 0 ? round(($positiveFeedback / $feedbackCount) * 100, 1) : null;
		$isTrustedTrader = $totalOrders >= 10 && $completionRate >= 95 && (($avgRating !== null && $avgRating >= 4.5) || $positiveFeedback >= 10);
		$isHighVolumeTrader = $completedOrders >= 50;
		$memberSince = $user?->created_at?->translatedFormat('M Y');

		$profileLabel = match (true) {
			$isTrustedTrader => __('Trusted Trader'),
			$isVerified => __('Verified Profile'),
			default => __('Growing Profile'),
		};

		$trustState = match (true) {
			$isTrustedTrader => 'is-trusted',
			$isVerified => 'is-verified',
			default => 'is-growing',
		};

		$trustScore = (int) round(min(
			99,
			($completionRate * 0.55)
			+ ((($avgRating ?? 0) / 5) * 30)
			+ (($positiveRate ?? 0) * 0.15)
			+ ($isVerified ? 8 : 0)
		));
		$trustScore = max($isVerified ? 42 : 18, $trustScore);
		$positiveRateLabel = $positiveRate !== null ? number_format($positiveRate, 1).'%' : __('New');
		$ratingLabel = $avgRatingValue ? $avgRatingValue.'/5' : __('No rating');

		$metrics = [
			['label' => __('Trades'), 'value' => number_format($completedOrders), 'note' => __('completed'), 'icon' => 'fa-check-double'],
			['label' => __('Completion'), 'value' => number_format($completionRate, 1).'%', 'note' => __('success rate'), 'icon' => 'fa-chart-line'],
			['label' => __('Rating'), 'value' => $avgRatingValue ? $avgRatingValue.'/5' : '—', 'note' => number_format($feedbackCount).' '.__('reviews'), 'icon' => 'fa-star'],
			['label' => __('Live Ads'), 'value' => number_format($activeAdsCount), 'note' => __('available'), 'icon' => 'fa-layer-group'],
		];

		$deskFacts = [
			['label' => __('Total orders'), 'value' => number_format($totalOrders), 'icon' => 'fa-receipt'],
			['label' => __('Positive feedback'), 'value' => $positiveRateLabel, 'icon' => 'fa-thumbs-up'],
			['label' => __('Member since'), 'value' => $memberSince ?: __('New'), 'icon' => 'fa-calendar-check'],
		];

		$highlights = [
			[
				'label' => __('Identity'),
				'value' => $isVerified ? __('KYC verified') : __('Verification pending'),
				'icon' => $isVerified ? 'fa-user-check' : 'fa-user-clock',
				'tone' => $isVerified ? 'success' : 'warning',
			],
			[
				'label' => __('Reliability'),
				'value' => number_format($completionRate, 1).'% '.__('completion'),
				'icon' => 'fa-chart-line',
				'tone' => $completionRate >= 95 ? 'success' : 'primary',
			],
			[
				'label' => __('Reputation'),
				'value' => $ratingLabel,
				'icon' => 'fa-star',
				'tone' => $avgRating !== null && $avgRating >= 4.5 ? 'success' : 'primary',
			],
		];

		$tradeNotes = [
			__('Match the payment holder name before sending funds.'),
			__('Start inside the advertised limit and keep the order chat open.'),
			__('Release crypto only after the incoming payment is fully settled.'),
		];
	@endphp
	
	<div class="single-form-card p2p-ui p2p-trader-profile">
		<x-user-feature-header
			:title="__('Trader Profile')"
			:subtitle="__('Check trust, reviews, and active ads before trading.')"
			icon="fas fa-user"
		>
			<a href="{{ route('user.p2p.offers.index') }}" class="btn btn-light-primary btn-sm p2p-btn-xs">
				<i class="fas fa-arrow-left"></i> @lang('Back')
			</a>
		</x-user-feature-header>
		
		<div class="card-main p2p-card-main">
			<section class="p2p-trader-hero p2p-trader-hero--{{ $trustState }}">
				<div class="p2p-trader-hero__top">
					<div class="p2p-trader-hero__identity">
						@if($user && ! empty($user->avatar))
							<div class="p2p-advertiser__avatar p2p-advertiser__avatar--img p2p-trader-hero__avatar">
								<img src="{{ asset($user->avatar_alt) }}" alt="{{ $userName }}" loading="lazy">
							</div>
						@else
							<div class="p2p-advertiser__avatar p2p-trader-hero__avatar">{{ $initials }}</div>
						@endif
						
						<div class="p2p-trader-hero__copy">
							<span class="p2p-trader-hero__eyebrow">@lang('P2P Trader Desk')</span>
							<h2 class="p2p-trader-hero__name">{{ $userName }}</h2>
							<p class="p2p-trader-hero__summary">
								@lang('Review trust signals, trading history, and live ads before opening an order.')
							</p>
							<div class="p2p-trader-hero__badges">
								@if($isVerified)
									<span class="p2p-seller-badge p2p-seller-badge--verified">
                                        <i class="fas fa-check-circle"></i>@lang('Verified')
                                    </span>
								@endif
								@if($isTrustedTrader)
									<span class="p2p-seller-badge p2p-seller-badge--trusted">
                                        <i class="fas fa-shield-alt"></i>@lang('Trusted')
                                    </span>
								@endif
								@if($isHighVolumeTrader)
									<span class="p2p-seller-badge p2p-seller-badge--volume">
                                        <i class="fas fa-bolt"></i>@lang('High Volume')
                                    </span>
								@endif
								<span class="p2p-trader-hero__meta">
                                    <i class="fas fa-calendar-alt"></i>
                                    @lang('Member since') {{ $memberSince ?: __('New') }}
                                </span>
							</div>
						</div>
					</div>
					
					<aside class="p2p-trader-hero__desk" aria-label="@lang('Trader confidence summary')">
						<div class="p2p-trader-hero__desk-top">
							<div>
								<span class="p2p-trader-hero__trust-label">@lang('Desk Confidence')</span>
								<div class="p2p-trader-hero__trust-score">{{ $trustScore }}<small>%</small></div>
							</div>
							<span class="p2p-trader-hero__trust-chip {{ $trustState }}">{{ $profileLabel }}</span>
						</div>
						<div class="p2p-trader-hero__confidence-bar" aria-hidden="true">
							<span style="width: {{ $trustScore }}%;"></span>
						</div>
						<div class="p2p-trader-hero__desk-grid">
							@foreach($deskFacts as $fact)
								<span class="p2p-trader-hero__desk-fact">
                                    <i class="fas {{ $fact['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $fact['label'] }}</span>
                                    <strong>{{ $fact['value'] }}</strong>
                                </span>
							@endforeach
						</div>
					</aside>
				</div>
				
				<div class="p2p-trader-hero__stats">
					@foreach($metrics as $metric)
						<article class="p2p-trader-stat-card">
							<span class="p2p-trader-stat-card__icon"><i class="fas {{ $metric['icon'] }}"></i></span>
							<div class="p2p-trader-stat-card__body">
								<span class="p2p-trader-stat-card__label">{{ $metric['label'] }}</span>
								<strong class="p2p-trader-stat-card__value">{{ $metric['value'] }}</strong>
								<span class="p2p-trader-stat-card__note">{{ $metric['note'] }}</span>
							</div>
						</article>
					@endforeach
				</div>
			</section>
			
			<div class="p2p-trader-highlights">
				@foreach($highlights as $highlight)
					<article class="p2p-trader-highlight p2p-trader-highlight--{{ $highlight['tone'] }}">
						<span class="p2p-trader-highlight__icon"><i class="fas {{ $highlight['icon'] }}" aria-hidden="true"></i></span>
						<span class="p2p-trader-highlight__copy">
                            <span>{{ $highlight['label'] }}</span>
                            <strong>{{ $highlight['value'] }}</strong>
                        </span>
					</article>
				@endforeach
			</div>
			
			<div class="row g-2 mt-0 p2p-trader-content-grid">
				<div class="col-12 col-xl-8">
					<section class="p2p-trader-section">
						<div class="p2p-trader-section__head">
							<div>
								<span class="p2p-trader-section__eyebrow">@lang('Market Feedback')</span>
								<h3 class="p2p-trader-section__title">@lang('Recent Reviews')</h3>
							</div>
							<div class="p2p-trader-section__meta">
								@if($feedbackCount > 0)
									<span class="p2p-trader-section__badge">{{ number_format($feedbackCount) }} @lang('reviews')</span>
								@endif
								@if($avgRatingValue)
									<span class="p2p-trader-section__badge">{{ $avgRatingValue }}/5 @lang('rating')</span>
								@endif
							</div>
						</div>
						
						<div class="p2p-trader-reviews">
							@forelse($feedbacks as $fb)
								<article class="p2p-trader-review-card">
									<div class="p2p-trader-review-card__head">
										<div>
											<h4 class="p2p-trader-review-card__name">{{ $fb->user->name ?? __('User') }}</h4>
											<span class="p2p-trader-review-card__time">{{ $fb->created_at->diffForHumans() }}</span>
										</div>
										<div class="p2p-trader-review-card__rating">
											@for($star = 1; $star <= 5; $star++)
												<i class="fas fa-star {{ $star <= (int) $fb->rating ? 'is-active' : '' }}"></i>
											@endfor
											<span>{{ $fb->rating }}/5</span>
										</div>
									</div>
									
									<p class="p2p-trader-review-card__comment">
										{{ filled($fb->comment) ? Str::limit((string) $fb->comment, 110) : __('No comment added.') }}
									</p>
								</article>
							@empty
								<x-user-not-found
									class="p2p-trader-empty"
									:title="__('No reviews yet')"
									:message="__('Review live ads, verification status, and payment terms before starting your first order.')"
									:eyebrow="__('Market feedback')"
									icon="fa-comments"
								/>
							@endforelse
						</div>
					</section>
				</div>
				
				<div class="col-12 col-xl-4">
					<section class="p2p-trader-section p2p-trader-section--tips">
						<div class="p2p-trader-section__head">
							<div>
								<span class="p2p-trader-section__eyebrow">@lang('Before You Trade')</span>
								<h3 class="p2p-trader-section__title">@lang('Desk Checklist')</h3>
							</div>
						</div>
						
						<ul class="p2p-trader-note-list">
							@foreach($tradeNotes as $note)
								<li class="p2p-trader-note-list__item">
									<span class="p2p-trader-note-list__icon"><i class="fas fa-check"></i></span>
									<span>{{ $note }}</span>
								</li>
							@endforeach
						</ul>
					</section>
				</div>
			</div>
			
			<section class="p2p-offers-panel p2p-offers-panel--marketplace p2p-trader-ads-panel mt-2">
				<div class="p2p-offers-panel__head">
					<div class="p2p-trader-section__head p2p-trader-section__head--inline">
						<div class="p2p-trader-ads-heading">
                            <span class="p2p-trader-ads-heading__icon" aria-hidden="true">
                                <i class="fas fa-store"></i>
                            </span>
							<div class="p2p-trader-ads-heading__copy">
								<span class="p2p-trader-section__eyebrow">@lang('Live Desk')</span>
								<h3 class="p2p-trader-section__title">@lang('Active Ads')</h3>
								<p class="p2p-trader-section__subtitle">@lang('Compare rate, payment method, and order limit before opening a trade.')</p>
							</div>
						</div>
						<div class="p2p-trader-section__meta">
							<span class="p2p-trader-section__badge"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ number_format($activeAdsCount) }} @lang('available')</span>
							<span class="p2p-trader-section__badge"><i class="fas fa-chart-line" aria-hidden="true"></i>{{ number_format($completionRate, 1) }}% @lang('completion')</span>
						</div>
					</div>
				</div>
				<div class="p2p-mkt-listhead" aria-hidden="true">
					<span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--advertiser">@lang('Asset')</span>
					<span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--price">@lang('Price')</span>
					<span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--limit">@lang('Available / Limit')</span>
					<span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--payment">@lang('Payment')</span>
					<span class="p2p-mkt-listhead__col p2p-mkt-listhead__col--trade">@lang('Trade')</span>
				</div>
				<div class="p2p-offers-panel__body p2p-trader-ads-panel__body">
					@include('frontend.user.p2p.trade_ads.partials._trade_ad_rows', ['offers' => $offers, 'sellerStats' => $sellerStats, 'marketplaceRow' => true, 'hideAdvertiser' => true])
				</div>
			</section>
			
			{{ $offers->links() }}
		</div>
	</div>
	
	@push('scripts')
		@include('frontend.user.p2p.trade_ads.partials._marketplace_scripts')
	@endpush
	@include('frontend.user.p2p.trade_ads.partials._marketplace_order_modal')
@endsection
