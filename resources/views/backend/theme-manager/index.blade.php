@extends('backend.layouts.app')
@section('title', __('Theme Manager'))

@push('styles')
	<link rel="stylesheet" href="{{ asset('backend/css/theme-manager-admin.css?v='.config('app.version')) }}">
@endpush

@push('scripts')
	<script src="{{ asset('backend/js/theme-manager-admin.js?v='.config('app.version')) }}"></script>
@endpush

@section('content')
	<div class="tm-admin">

		{{-- ====== Hero ====== --}}
		<section class="tm-hero">
			<div class="tm-hero__inner">
				<div class="tm-hero__copy">
					<span class="tm-hero__eyebrow">
						<span class="tm-hero__eyebrow-dot"></span>
						{{ __('Appearance') }}
					</span>
					<h1 class="tm-hero__title">{{ __('Theme Manager') }}</h1>
					<p class="tm-hero__subtitle">
						{{ __('Pick the visual identity for your public landing. The active theme drives both the page-builder library and the frontend look.') }}
					</p>
				</div>

				<div class="tm-hero__meta">
					<div class="tm-hero__chip" style="--tm-accent: {{ $activeTheme->accentColor() }}">
						<span class="tm-hero__chip-label">{{ __('Active') }}</span>
						<span class="tm-hero__chip-value">
							<span class="tm-hero__pulse"></span>
							{{ $activeTheme->label() }}
						</span>
					</div>
					<div class="tm-hero__stats">
						<span class="tm-hero__stat">
							<strong>{{ count($themes) }}</strong>
							<small>{{ __('Themes') }}</small>
						</span>
						<span class="tm-hero__stat-sep"></span>
						<span class="tm-hero__stat">
							<strong>{{ $themes->where('is_active', true)->first()['component_count'] ?? 0 }}</strong>
							<small>{{ __('Blocks') }}</small>
						</span>
						<span class="tm-hero__stat-sep"></span>
						<span class="tm-hero__stat">
							<strong>{{ $activeTheme->isDark() ? __('Dark') : __('Light') }}</strong>
							<small>{{ __('Mode') }}</small>
						</span>
					</div>
				</div>
			</div>
		</section>

		{{-- ====== Theme cards ====== --}}
		<div class="tm-grid">
			@foreach($themes as $theme)
				<article class="tm-card {{ $theme['is_active'] ? 'is-active' : '' }} {{ $theme['is_dark'] ? 'is-dark' : 'is-light' }}"
				         style="--tm-accent: {{ $theme['accent_color'] }}">

					{{-- Preview --}}
					<div class="tm-card__preview">
						@if(file_exists(public_path($theme['preview_image'])))
							<img src="{{ asset($theme['preview_image']) }}" alt="{{ $theme['label'] }} preview" loading="lazy">
						@else
							@include('backend.theme-manager.partials._preview_mock', ['theme' => $theme])
						@endif
						@if($theme['is_active'])
							<span class="tm-card__badge">
								<svg width="11" height="11" viewBox="0 0 24 24" fill="none">
									<path d="M5 12.5L10 17.5L19 7.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
								{{ __('Active') }}
							</span>
						@endif
						<span class="tm-card__overlay-mode">
							{{ $theme['is_dark'] ? __('Dark') : __('Light') }}
						</span>
					</div>

					{{-- Body --}}
					<div class="tm-card__body">
						<div class="tm-card__head">
							<div class="tm-card__head-text">
								<span class="tm-card__kicker">{{ __('Theme') }} · #{{ str_pad((string)($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
								<h3 class="tm-card__title">{{ $theme['label'] }}</h3>
							</div>
							<div class="tm-card__head-meta">
								<span class="tm-card__chip">
									<svg width="10" height="10" viewBox="0 0 24 24" fill="none">
										<rect x="4" y="4" width="7" height="7" rx="1.4" fill="currentColor" opacity=".55"/>
										<rect x="13" y="4" width="7" height="7" rx="1.4" fill="currentColor"/>
										<rect x="4" y="13" width="7" height="7" rx="1.4" fill="currentColor"/>
										<rect x="13" y="13" width="7" height="7" rx="1.4" fill="currentColor" opacity=".55"/>
									</svg>
									{{ $theme['component_count'] }}
								</span>
								<span class="tm-card__swatch" title="{{ $theme['accent_color'] }}">
									<span class="tm-card__swatch-chip"></span>
									<code>{{ $theme['accent_color'] }}</code>
								</span>
							</div>
						</div>

						<p class="tm-card__tagline">{{ $theme['tagline'] }}</p>

						<div class="tm-actionbar">
							@if($theme['is_active'])
								<button type="button" class="btn btn-outline-primary tm-actionbar__primary" disabled>
									<i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>{{ __('In Use') }}
								</button>
							@else
								<form method="POST" action="{{ route('admin.theme-manager.activate', $theme['value']) }}" class="tm-actionbar__form">
									@csrf
									<button type="submit" class="btn btn-primary tm-actionbar__primary">
										<i class="fa-solid fa-check me-1" aria-hidden="true"></i>{{ __('Activate') }}
									</button>
								</form>
							@endif

							@if(auth()->user()?->can('component-list'))
								<a href="{{ route('admin.page.component.index') }}"
								   class="btn btn-outline-secondary"
								   title="{{ __('View this theme\'s blocks') }}">
									<i class="fa-solid fa-cubes me-1" aria-hidden="true"></i>{{ __('Blocks') }}
								</a>
							@endif

							<a href="{{ route('admin.page.site.index') }}"
							   class="btn btn-outline-secondary tm-actionbar__icon"
							   title="{{ __('Open Page Builder') }}" aria-label="{{ __('Open Page Builder') }}">
								<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
							</a>
						</div>
					</div>
				</article>
			@endforeach
		</div>

		{{-- ====== Landing Colours ====== --}}
		@include('backend.theme-manager.partials._color_studio')

		{{-- ====== How it works ====== --}}
		<section class="tm-note">
			<div class="tm-note__head">
				<span class="tm-note__badge" aria-hidden="true">
					<svg width="13" height="13" viewBox="0 0 24 24" fill="none">
						<path d="M12 4L14 9L20 10L15.5 14L17 20L12 17L7 20L8.5 14L4 10L10 9L12 4Z"
						      stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
					</svg>
				</span>
				<div>
					<span class="tm-note__kicker">{{ __('Quickstart') }}</span>
					<h4 class="tm-note__title">{{ __('How it works') }}</h4>
				</div>
			</div>
			<ol class="tm-note__list">
				<li>
					<span class="tm-note__step">01</span>
					<span>{{ __('Activate the theme you want — your choice is saved instantly and applies site-wide.') }}</span>
				</li>
				<li>
					<span class="tm-note__step">02</span>
					<span>{{ __('Open Site Builder → All Pages → Edit your home page. The component library on the left now shows only the active theme\'s blocks.') }}</span>
				</li>
				<li>
					<span class="tm-note__step">03</span>
					<span>{{ __('Drag-and-drop blocks into the page, save, and the public landing renders with the new layout, CSS and JS — no rebuild required.') }}</span>
				</li>
				<li>
					<span class="tm-note__step">04</span>
					<span>{{ __('Switching back is non-destructive: each theme\'s components are stored independently so your data is preserved on both sides.') }}</span>
				</li>
			</ol>
		</section>

	</div>
@endsection
