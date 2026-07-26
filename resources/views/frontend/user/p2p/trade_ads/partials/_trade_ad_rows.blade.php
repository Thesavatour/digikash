@php use App\Enums\P2P\OrderSide;use App\Enums\P2P\PromotionStatus; @endphp
@forelse($offers as $offer)
	@php
		$compactSeller = $compactSeller ?? false;
		$marketplaceRow = $marketplaceRow ?? false;
		$hideAdvertiser = $hideAdvertiser ?? false;
		$priceTrends = $priceTrends ?? [];
		$currency = $offer->wallet->currency->code;
		$assetKey = strtolower($currency);
		$currencyFlag = $offer->wallet->currency->flag ?? null;
		$isSelf = (int) $offer->user_id === (int) auth()->id();
		$user = $offer->user;
		$userName = $offer->user?->name ?? __('User');
		$initials = strtoupper(substr((string) $offer->user?->first_name, 0, 1) . substr((string) $offer->user?->last_name, 0, 1));
		$initials = $initials !== '' ? $initials : strtoupper(substr((string) $userName, 0, 1));
		$isBuy = $offer->side === OrderSide::SELL;
		$btnLabel = ($isBuy ? __('Buy') : __('Sell')) . ' ' . $currency;
		$btnIcon = $isBuy ? 'fa-shopping-cart' : 'fa-tag';
		$actionModifier = $isBuy ? 'p2p-offer-action-btn--buy' : 'p2p-offer-action-btn--sell';
		$decimals = (int) setting('site_decimal', 2);
		$stats = $sellerStats[(int) $offer->user_id] ?? null;
		$completedOrders = (int) ($stats['completed_orders'] ?? 0);
		$totalOrders = (int) ($stats['total_orders'] ?? 0);
		$completionRate = (float) ($stats['completion_rate'] ?? 0);
		$avgRating = $stats['avg_rating'] ?? null;
		$positiveFeedback = (int) ($stats['positive_feedback'] ?? 0);
		$isVerified = $user ? $user->isKycVerified() : false;
		$isTrusted = $totalOrders >= 10 && $completionRate >= 95 && (($avgRating !== null && $avgRating >= 4.5) || $positiveFeedback >= 10);
		$isHighVolume = $completedOrders >= 50;
		$isOnline = $user?->isOnline() ?? false;
		$presenceLabel = $isOnline ? __('Online now') : __('Offline');

		$trend = $priceTrends[(int) $offer->id] ?? null;
		$trendDirection = $trend['direction'] ?? null;
		$trendPct = $trend !== null ? number_format((float) $trend['pct'], 2) : null;
		$trendIcon = match ($trendDirection) {
			'up' => 'fa-caret-up',
			'down' => 'fa-caret-down',
			'flat' => 'fa-minus',
			default => null,
		};
		$trendText = match ($trendDirection) {
			'up', 'down' => $trendPct . '%',
			'flat' => __('Market'),
			default => null,
		};
		$trendTitle = match ($trendDirection) {
			'up' => $trendPct . '% ' . __('better than the current market average price'),
			'down' => $trendPct . '% ' . __('worse than the current market average price'),
			'flat' => __('In line with the current market average price'),
			default => null,
		};
		$fiatCurrency = $offer->quote_currency_code;
		$fmtAmount = fn (float $value): string => fmod($value, 1.0) === 0.0
			? number_format($value)
			: number_format($value, $decimals);
		$availableText = $offer->max_amount !== null
			? $fmtAmount((float) $offer->max_amount)
			: __('Unlimited');
		$limitText = $fmtAmount((float) $offer->min_amount) . ' – ' . ($offer->max_amount !== null ? $fmtAmount((float) $offer->max_amount) : __('Unlimited'));
		$hasActiveOrders = (int) ($offer->active_orders_count ?? 0) > 0;
		$selfActionLabel = __('Manage Ad');

		$promotion = $offer->promotion;
		$isSponsored = $promotion
			&& $promotion->status === PromotionStatus::ACTIVE
			&& $promotion->ends_at
			&& $promotion->ends_at->isFuture();
		$promotionFeatures = (array) ($promotion?->package?->features ?? []);
		$hasHighlightedCard = $isSponsored && !empty($promotionFeatures['highlighted_card']);
		$hasFeaturedBadge = $isSponsored
			&& (!empty($promotionFeatures['featured_badge']) || !empty($promotionFeatures['verified_badge']));
		$accentColor = strtoupper(trim((string) ($promotion?->package?->accent_color ?? '')));
		$hasAccentColor = in_array($accentColor, ['GOLD', 'BLUE', 'RED'], true);
		$accentCardClass = $hasAccentColor ? match ($accentColor) {
			'GOLD' => 'p2p-offer-card--accent-gold',
			'RED' => 'p2p-offer-card--accent-red',
			default => 'p2p-offer-card--accent-blue',
		} : '';
		$hasSoftAccentBorder = $isSponsored && !$hasHighlightedCard && $hasAccentColor;
		$cardClass = implode(' ', array_filter([
			'p2p-offer-card',
			$compactSeller ? 'p2p-offer-card--profile' : null,
			$isSponsored ? 'p2p-offer-card--sponsored' : null,
			$hasHighlightedCard ? 'p2p-offer-card--highlighted' : null,
			$hasSoftAccentBorder ? 'p2p-offer-card--accent-soft' : null,
			$accentCardClass,
			$extraClass ?? null
		]));

		$pmMethods = $offer->paymentMethods->filter(fn ($m) => !empty($m?->name))->values();
		$pmNames = $pmMethods->pluck('name')->filter()->values();
		$pmAll = $pmNames->implode(', ');
		$pmJson = $pmMethods->map(function ($m) {
			return [
				'id' => (int) $m->id,
				'name' => (string) $m->name,
				'logo' => !empty($m->logo) ? asset('storage/'.$m->logo) : null,
				'instructions' => (string) ($m->instructions ?? ''),
			];
		})->values();
		$advertiserUrl = route('user.p2p.advertisers.show', $offer->user_id);
		$paymentPreviewCount = $compactSeller ? 2 : 3;

		$adSideLabel = $offer->side->label();
		$adSideMod = $offer->side === OrderSide::BUY ? 'buy' : 'sell';
		$avatarGradients = [['#2E7CF6', '#6F5BFF'], ['#0EA5A4', '#34D399'], ['#F59E0B', '#F97316'], ['#8B5CF6', '#D946EF'], ['#0EA5E9', '#2563EB'], ['#F43F5E', '#FB7185'], ['#10B981', '#0E9F6E'], ['#64748B', '#94A3B8']];
		$avatarGradient = $avatarGradients[(int) $offer->user_id % count($avatarGradients)];
		$pmBarPalette = ['#9FE870', '#0070E0', '#E2136E', '#F6921E', '#F0B90B', '#6366F1', '#811E5C', '#26A17B', '#2E7CF6', '#0EA5A4'];

		$mktRowClass = implode(' ', array_filter([
			'p2p-mkt-row',
			$isSponsored ? 'p2p-mkt-row--sponsored' : null,
			$hasHighlightedCard ? 'p2p-mkt-row--highlighted' : null,
			$extraClass ?? null,
		]));
	@endphp

	@if($marketplaceRow)
		<div class="{{ $mktRowClass }}">
			@if($hideAdvertiser)
				<div class="p2p-mkt-col p2p-mkt-col--advertiser p2p-mkt-col--asset">
					@if(! empty($currencyFlag))
						<img class="p2p-mkt-asset-badge p2p-mkt-asset-badge--img" src="{{ asset($currencyFlag) }}" alt="{{ $currency }}" loading="lazy">
					@else
						<span class="p2p-mkt-asset-badge p2p-asset-icon--{{ $assetKey }}">{{ strtoupper(substr($currency, 0, 1)) }}</span>
					@endif
					<span class="p2p-mkt-asset-meta">
						<span class="p2p-mkt-asset-code">{{ $currency }}</span>
						<span class="p2p-mkt-asset-side p2p-mkt-asset-side--{{ $adSideMod }}">{{ $adSideLabel }}</span>
					</span>
				</div>
				@else
				{{-- Advertiser --}}
			<div class="p2p-mkt-col p2p-mkt-col--advertiser">
				<span class="p2p-mkt-avatar">
					@if($user && ! empty($user->avatar))
						<img class="p2p-mkt-avatar__img" src="{{ asset($user->avatar_alt) }}" alt="{{ $userName }}" loading="lazy">
					@else
						<span class="p2p-mkt-avatar__initials" style="background: linear-gradient(135deg, {{ $avatarGradient[0] }}, {{ $avatarGradient[1] }});">{{ $initials }}</span>
					@endif
					<span class="p2p-mkt-avatar__status {{ $isOnline ? 'is-online' : 'is-offline' }}" title="{{ $presenceLabel }}" aria-label="{{ $presenceLabel }}"></span>
				</span>
				<div class="p2p-mkt-advertiser">
					<div class="p2p-mkt-advertiser__top">
						<a class="p2p-mkt-advertiser__name" href="{{ $advertiserUrl }}">{{ $userName }}</a>
						@if($isVerified)
							<i class="fas fa-check-circle p2p-mkt-verified" title="@lang('Verified merchant')" aria-label="@lang('Verified merchant')"></i>
						@endif
						@if($isTrusted)
							<span class="p2p-mkt-chip p2p-mkt-chip--trusted" title="@lang('Trusted merchant')"><i class="fas fa-shield-alt"></i>@lang('Trusted')</span>
						@endif
						@if($isHighVolume)
							<span class="p2p-mkt-chip p2p-mkt-chip--volume" title="@lang('High volume merchant')"><i class="fas fa-bolt"></i>@lang('High Volume')</span>
						@endif
						@if($isSponsored)
							<span class="p2p-mkt-chip p2p-mkt-chip--promoted"><i class="fas fa-bullhorn"></i>@lang('Promoted')</span>
							@if($hasFeaturedBadge)
								<span class="p2p-mkt-chip p2p-mkt-chip--featured"><i class="fas fa-star"></i>@lang('Featured')</span>
							@endif
						@endif
					</div>
					<span class="p2p-mkt-advertiser__stats">{{ number_format($completionRate, 1) }}% @lang('completion') <span class="p2p-mkt-sep">·</span> {{ number_format($totalOrders) }} @lang('trades')</span>
				</div>
			</div>

			<div class="p2p-mkt-hr"></div>

			@endif

				{{-- Price --}}
			<div class="p2p-mkt-col p2p-mkt-col--price">
				<div class="p2p-mkt-price-line">
					<span class="p2p-mkt-price">{{ number_format((float) $offer->price, $decimals) }}</span>
					<span class="p2p-mkt-price__fiat">{{ $fiatCurrency }}</span>
				</div>
				<div class="p2p-mkt-price-sub">
					<span class="p2p-mkt-price__per">@lang('per') {{ $currency }}</span>
					@if($trendIcon)
						<span class="p2p-offer-trend p2p-offer-trend--{{ $trendDirection }}" title="{{ $trendTitle }}"><i class="fas {{ $trendIcon }}" aria-hidden="true"></i>{{ $trendText }}</span>
					@endif
				</div>
			</div>

			{{-- Available / Limit --}}
			<div class="p2p-mkt-col p2p-mkt-col--limit">
				<div class="p2p-mkt-kv">
					<span class="p2p-mkt-kv__k">@lang('Available')</span>
					<span class="p2p-mkt-kv__v">{{ $availableText }} {{ $currency }}</span>
				</div>
				<div class="p2p-mkt-kv">
					<span class="p2p-mkt-kv__k">@lang('Limit')</span>
					<span class="p2p-mkt-kv__v">{{ $limitText }} {{ $currency }}</span>
				</div>
			</div>

			{{-- Payment --}}
			<div class="p2p-mkt-col p2p-mkt-col--payment">
				@if($pmNames->isNotEmpty())
					<span class="p2p-mkt-pms" tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-container="body" data-bs-content="{{ $pmAll }}">
						@foreach($pmMethods->take($paymentPreviewCount) as $pm)
							<span class="p2p-mkt-pm" title="{{ $pm->name }}">
								<span class="p2p-mkt-pm__icon" style="--p2p-pm-icon-bg: {{ $pmBarPalette[crc32((string) $pm->name) % count($pmBarPalette)] }};">
									@if(! empty($pm->logo))
										<img class="p2p-mkt-pm__logo" src="{{ asset('storage/'.$pm->logo) }}" alt="{{ $pm->name }}" loading="lazy">
									@else
										<span class="p2p-mkt-pm__fallback" aria-hidden="true">{{ strtoupper(substr((string) $pm->name, 0, 1)) }}</span>
									@endif
								</span>
								<span class="p2p-mkt-pm__name">{{ $pm->name }}</span>
							</span>
						@endforeach
						@if($pmNames->count() > $paymentPreviewCount)
							<span class="p2p-mkt-pm p2p-mkt-pm--more">+{{ $pmNames->count() - $paymentPreviewCount }}</span>
						@endif
					</span>
				@else
					<span class="p2p-mkt-pm-empty">@lang('No payment methods')</span>
				@endif
			</div>

			<div class="p2p-mkt-hr"></div>

			{{-- Action --}}
			<div class="p2p-mkt-col p2p-mkt-col--trade">
				@if($isSelf)
					<a href="{{ route('user.p2p.offers.edit', $offer) }}" class="p2p-mkt-btn p2p-mkt-btn--self" title="@lang('Manage your trade ad')">
						<i class="fas fa-sliders-h"></i> {{ $selfActionLabel }}
					</a>
					@if($hasActiveOrders)
						<span class="p2p-mkt-esttime p2p-mkt-esttime--warning"><i class="fas fa-circle-info"></i> @lang('Active order running')</span>
					@else
						<span class="p2p-mkt-esttime"><i class="far fa-clock"></i> @lang('Est. time') ~{{ (int) ($offer->payment_window_minutes ?? 0) }} @lang('min')</span>
					@endif
				@else
					<button type="button"
					        class="p2p-mkt-btn {{ $isBuy ? 'p2p-mkt-btn--buy' : 'p2p-mkt-btn--sell' }}"
					        data-bs-toggle="modal"
					        data-bs-target="#p2pOrderModal"
					        data-offer-id="{{ $offer->id }}"
					        data-side="{{ $offer->side->value }}"
					        data-min="{{ $offer->min_amount }}"
					        data-max="{{ $offer->max_amount ?? '' }}"
					        data-price="{{ $offer->price }}"
					        data-currency="{{ $currency }}"
					        data-currency-flag="{{ ! empty($currencyFlag) ? asset($currencyFlag) : '' }}"
					        data-fiat-currency="{{ $fiatCurrency }}"
					        data-action="{{ $isBuy ? 'buy' : 'sell' }}"
					        data-action-label="{{ $btnLabel }}"
					        data-available-text="{{ $availableText }}"
					        data-limit-text="{{ $limitText }}"
					        data-user-name="{{ $userName }}"
					        data-user-initials="{{ $initials }}"
					        data-user-avatar="{{ $user && ! empty($user->avatar) ? asset($user->avatar_alt) : '' }}"
					        data-user-verified="{{ $isVerified ? '1' : '0' }}"
					        data-completion-rate="{{ number_format($completionRate, 1) }}"
					        data-total-trades="{{ $totalOrders }}"
					        data-payment-methods="{{ e($pmJson->toJson()) }}"
					        data-payment-window="{{ (int) ($offer->payment_window_minutes ?? 0) }}"
					        data-trend-direction="{{ $trendDirection ?? '' }}"
					        data-trend-text="{{ $trendText ?? '' }}"
					        data-trend-title="{{ $trendTitle ?? '' }}"
					        data-advertiser-url="{{ $advertiserUrl }}"
					        data-terms="{{ e(json_encode($offer->terms_text)) }}">
						{{ $btnLabel }}
					</button>
					<span class="p2p-mkt-esttime"><i class="far fa-clock"></i> @lang('Est. time') ~{{ (int) ($offer->payment_window_minutes ?? 0) }} @lang('min')</span>
				@endif
			</div>
		</div>
	@else
	<div class="{{ $cardClass }}">
		<div class="p2p-offer-card__head">
			<div class="p2p-offer-card__seller {{ $compactSeller ? 'p2p-offer-card__seller--profile' : '' }}">
				@if($compactSeller)
					<div class="p2p-offer-profile-context">
						<span class="{{ $offer->side->badgeClass() }} p2p-offer-profile-context__side">{{ $offer->side->label() }}</span>
						<div class="p2p-offer-profile-context__copy">
							<strong class="p2p-offer-profile-context__title">{{ $btnLabel }}</strong>
							<span class="p2p-offer-profile-context__meta">{{ (int) ($offer->payment_window_minutes ?? 0) }} @lang('min')</span>
						</div>
					</div>
				@else
					<div class="p2p-advertiser">
						@if($user && ! empty($user->avatar))
							<div class="p2p-advertiser__avatar p2p-advertiser__avatar--img">
								<img src="{{ asset($user->avatar_alt) }}" alt="{{ $userName }}" loading="lazy">
								<span class="p2p-advertiser__status {{ $isOnline ? 'p2p-advertiser__status--online' : 'p2p-advertiser__status--offline' }}" title="{{ $presenceLabel }}" aria-label="{{ $presenceLabel }}"></span>
							</div>
						@else
							<div class="p2p-advertiser__avatar">{{ $initials }}<span class="p2p-advertiser__status {{ $isOnline ? 'p2p-advertiser__status--online' : 'p2p-advertiser__status--offline' }}" title="{{ $presenceLabel }}" aria-label="{{ $presenceLabel }}"></span></div>
						@endif
						<div class="p2p-advertiser__meta">
							<div class="p2p-advertiser__name-row">
								<div class="p2p-advertiser__name">
									<a class="p2p-advertiser__name-link" href="{{ route('user.p2p.advertisers.show', $offer->user_id) }}">{{ $userName }}</a>
								</div>
								@if($isVerified)
									<span class="p2p-seller-badge p2p-seller-badge--verified p2p-seller-badge--verified-inline"><i class="fas fa-check-circle"></i>@lang('Verified')</span>
								@endif
							</div>

							<div class="p2p-advertiser__stats">
								{{ number_format($completionRate, 1) }}% @lang('completion')
								<span class="p2p-dot">|</span>
								{{ number_format($totalOrders) }} @lang('trades')
							</div>
						</div>
					</div>
				@endif
			</div>

			<div class="p2p-offer-card__aside">
				<div class="p2p-offer-card__meta-row">
                    <span class="p2p-available-badge {{ $compactSeller ? 'p2p-available-badge--compact' : '' }}">
                        @unless($compactSeller)
		                    <span class="p2p-available-badge__label">@lang('Available'):</span>
	                    @endunless
                        <span class="p2p-available-badge__value">{{ $availableText }} {{ $currency }}</span>
                    </span>
				</div>

				<div class="p2p-offer-card__badges p2p-offer-card__promo-row">
					@if(! $compactSeller && $isTrusted)
						<span class="p2p-seller-badge p2p-seller-badge--trusted p2p-seller-badge--compact"><i class="fas fa-shield-alt"></i>@lang('Trusted')</span>
					@endif
					@if(! $compactSeller && $isHighVolume)
						<span class="p2p-seller-badge p2p-seller-badge--volume p2p-seller-badge--compact"><i class="fas fa-bolt"></i>@lang('High Volume')</span>
					@endif
					@if($isSponsored)
						<span class="p2p-offer-chip p2p-offer-chip--promoted"><i class="fas fa-bullhorn"></i>@lang('Promoted')</span>
						@if($hasFeaturedBadge)
							<span class="p2p-offer-chip p2p-offer-chip--featured"><i class="fas fa-star"></i>@lang('Featured')</span>
						@endif
					@endif
				</div>
			</div>
		</div>

		<div class="p2p-offer-body">
			<div class="p2p-offer-body__left">
				<div class="p2p-offer-price-line">
                    <span class="p2p-offer-price-base">
                        @if(!empty($currencyFlag))
		                    <img class="p2p-asset-logo" src="{{ asset($currencyFlag) }}" alt="{{ $currency }}" loading="lazy">
	                    @else
		                    <span class="p2p-asset-icon p2p-asset-icon--{{ $assetKey }}">{{ strtoupper(substr($currency, 0, 1)) }}</span>
	                    @endif
	                    {{ $currency }}
                    </span>
					<span class="p2p-offer-price-arrow">-&gt;</span>
					<span class="p2p-offer-price-number">{{ number_format((float) $offer->price, $decimals) }}</span>
					<span class="p2p-offer-price-fiat">{{ $fiatCurrency }}</span>
					<span class="p2p-offer-price-per">@lang('per') {{ $currency }}</span>
					@if($trendIcon)
						<span class="p2p-offer-trend p2p-offer-trend--{{ $trendDirection }}" title="{{ $trendTitle }}">
							<i class="fas {{ $trendIcon }}" aria-hidden="true"></i>{{ $trendText }}
						</span>
					@endif
				</div>

				@if($compactSeller)
					<div class="p2p-offer-compact-meta">
						<div class="p2p-offer-compact-meta__item">
							<span class="p2p-offer-compact-meta__label">@lang('Range')</span>
							<span class="p2p-offer-compact-meta__value">{{ $limitText }} {{ $currency }}</span>
						</div>

						<div class="p2p-offer-compact-meta__item">
							<span class="p2p-offer-compact-meta__label">@lang('Methods')</span>
							@if($pmNames->isNotEmpty())
								<span class="p2p-payment-pills" tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-container="body"
								      data-bs-content="{{ $pmAll }}">
                                    @foreach($pmMethods->take($paymentPreviewCount) as $pm)
										<span class="p2p-payment-pill">
                                            @if(! empty($pm->logo))
												<img class="p2p-payment-pill__logo" src="{{ asset('storage/'.$pm->logo) }}" alt="{{ $pm->name }}" loading="lazy">
											@else
												<span class="p2p-payment-pill__fallback" aria-hidden="true">{{ strtoupper(substr((string) $pm->name, 0, 1)) }}</span>
											@endif
                                            <span class="p2p-payment-pill__text">{{ $pm->name }}</span>
                                        </span>
									@endforeach
									@if($pmNames->count() > $paymentPreviewCount)
										<span class="p2p-payment-pill">+{{ $pmNames->count() - $paymentPreviewCount }}</span>
									@endif
                                </span>
							@else
								<span class="text-muted">@lang('No payment methods')</span>
							@endif
						</div>
					</div>
				@else
					<div class="p2p-offer-limit">
						<span class="p2p-offer-limit__label">@lang('Limit'):</span>
						<span class="p2p-offer-limit__value">{{ $limitText }} {{ $currency }}</span>
					</div>

					<div class="p2p-offer-payment-row">
						<span class="p2p-offer-payment__label">@lang('Payment'):</span>
						@if($pmNames->isNotEmpty())
							<span class="p2p-payment-pills" tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-container="body"
							      data-bs-content="{{ $pmAll }}">
                                @foreach($pmMethods->take($paymentPreviewCount) as $pm)
									<span class="p2p-payment-pill">
                                        @if(! empty($pm->logo))
											<img class="p2p-payment-pill__logo" src="{{ asset('storage/'.$pm->logo) }}" alt="{{ $pm->name }}" loading="lazy">
										@else
											<span class="p2p-payment-pill__fallback" aria-hidden="true">{{ strtoupper(substr((string) $pm->name, 0, 1)) }}</span>
										@endif
                                        <span class="p2p-payment-pill__text">{{ $pm->name }}</span>
                                    </span>
								@endforeach
								@if($pmNames->count() > $paymentPreviewCount)
									<span class="p2p-payment-pill">+{{ $pmNames->count() - $paymentPreviewCount }}</span>
								@endif
                            </span>
						@else
							<span class="text-muted">@lang('No payment methods')</span>
						@endif
					</div>
				@endif
			</div>

			<div class="p2p-offer-body__right">
				<div class="p2p-offer-cta">
					@if($isSelf)
						<a href="{{ route('user.p2p.offers.edit', $offer) }}" class="btn btn-sm p2p-offer-action-btn p2p-offer-action-btn--lg p2p-offer-action-btn--self"
						   title="@lang('Manage your trade ad')">
							<i class="fas fa-sliders-h"></i>
							<span class="p2p-offer-action-btn__text">{{ $selfActionLabel }}</span>
						</a>
						@if($hasActiveOrders)
							<div class="p2p-operation-time p2p-operation-time--warning">
								<i class="fas fa-circle-info"></i>
								@lang('Active order running')
							</div>
						@elseif(! $compactSeller)
							<div class="p2p-operation-time">
								<i class="fas fa-user-gear"></i>
								@lang('Manage tools')
							</div>
						@endif
					@else
						<button type="button"
						        class="btn btn-sm p2p-offer-action-btn p2p-offer-action-btn--lg {{ $actionModifier }}"
						        data-bs-toggle="modal"
						        data-bs-target="#p2pOrderModal"
						        data-offer-id="{{ $offer->id }}"
						        data-side="{{ $offer->side->value }}"
						        data-min="{{ $offer->min_amount }}"
						        data-max="{{ $offer->max_amount ?? '' }}"
						        data-price="{{ $offer->price }}"
						        data-currency="{{ $currency }}"
						        data-currency-flag="{{ ! empty($currencyFlag) ? asset($currencyFlag) : '' }}"
						        data-fiat-currency="{{ $fiatCurrency }}"
						        data-action="{{ $isBuy ? 'buy' : 'sell' }}"
						        data-action-label="{{ $btnLabel }}"
						        data-available-text="{{ $availableText }}"
						        data-limit-text="{{ $limitText }}"
						        data-user-name="{{ $userName }}"
						        data-user-initials="{{ $initials }}"
						        data-user-avatar="{{ $user && ! empty($user->avatar) ? asset($user->avatar_alt) : '' }}"
						        data-user-verified="{{ $isVerified ? '1' : '0' }}"
						        data-completion-rate="{{ number_format($completionRate, 1) }}"
						        data-total-trades="{{ $totalOrders }}"
						        data-payment-methods="{{ e($pmJson->toJson()) }}"
						        data-payment-window="{{ (int) ($offer->payment_window_minutes ?? 0) }}"
						        data-trend-direction="{{ $trendDirection ?? '' }}"
						        data-trend-text="{{ $trendText ?? '' }}"
						        data-trend-title="{{ $trendTitle ?? '' }}"
						        data-advertiser-url="{{ $advertiserUrl }}"
						        data-terms="{{ e(json_encode($offer->terms_text)) }}">
							<i class="fas {{ $btnIcon }}"></i>
							<span class="p2p-offer-action-btn__text">{{ $btnLabel }}</span>
						</button>
					@endif

					@if(! $compactSeller)
						<div class="p2p-operation-time">
							<i class="far fa-clock"></i>
							@lang('Est. time'):
							~{{ (int) ($offer->payment_window_minutes ?? 0) }} @lang('min')
						</div>
					@endif
				</div>
			</div>
		</div>
	</div>
	@endif
@empty
	<x-user-not-found
		:title="__('No trade ads found')"
		:message="__('Trade ads matching the current filters will appear here.')"
		:eyebrow="__('P2P marketplace')"
		icon="fa-store"
		class="p2p-empty"
	/>
@endforelse
