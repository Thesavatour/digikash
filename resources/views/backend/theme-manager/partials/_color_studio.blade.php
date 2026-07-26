{{-- ====== Landing Colours studio ====== --}}
<section class="tm-colors" aria-labelledby="tm-colors-title">
	<div class="tm-colors__head">
		<span class="tm-colors__badge" aria-hidden="true">
			<i class="fa-solid fa-palette"></i>
		</span>
		<div class="tm-colors__head-text">
			<span class="tm-colors__kicker">{{ __('Appearance') }}</span>
			<h4 class="tm-colors__title" id="tm-colors-title">{{ __('Landing Colours') }}</h4>
			<p class="tm-colors__subtitle">
				{{ __('Tune each theme\'s public landing palette. The active theme renders these colours across your marketing pages — dashboards are unaffected.') }}
			</p>
		</div>
	</div>

	{{-- Segmented theme switch --}}
	<div class="tm-colors__switch" role="tablist" aria-label="{{ __('Choose a theme to edit') }}">
		@foreach($palettes as $palette)
			@php($isActive = $palette['value'] === $activeTheme->value)
			<button type="button"
			        class="tm-colors__seg {{ $isActive ? 'is-selected' : '' }}"
			        data-theme-target="{{ $palette['value'] }}"
			        role="tab"
			        aria-selected="{{ $isActive ? 'true' : 'false' }}"
			        aria-controls="tm-colors-panel-{{ $palette['value'] }}">
				<span class="tm-colors__seg-dot" style="--seg: {{ $palette['accent_color'] }}"></span>
				{{ $palette['label'] }}
				@if($isActive)
					<span class="tm-colors__seg-live">{{ __('Active') }}</span>
				@endif
			</button>
		@endforeach
	</div>

	{{-- Panels --}}
	@foreach($palettes as $palette)
		@php($isActive = $palette['value'] === $activeTheme->value)
		<div class="tm-colors__panel {{ $isActive ? 'is-shown' : '' }}"
		     id="tm-colors-panel-{{ $palette['value'] }}"
		     data-theme-panel="{{ $palette['value'] }}"
		     role="tabpanel">

			<form method="POST"
			      action="{{ route('admin.theme-manager.colors.update', $palette['value']) }}"
			      class="tm-colors__form"
			      id="tm-colors-form-{{ $palette['value'] }}">
				@csrf

				<div class="tm-colors__grid">
					@foreach($palette['slots'] as $slot)
						@php($fieldId = 'tm-'.$palette['value'].'-'.$slot['key'])
						<div class="tm-swatch" data-slot="{{ $slot['key'] }}" data-default="{{ $slot['default'] }}">
							<div class="tm-swatch__top">
								<label class="tm-swatch__label" for="{{ $fieldId }}">{{ $slot['label'] }}</label>
								<button type="button" class="tm-swatch__reset"
								        title="{{ __('Reset :label to default', ['label' => $slot['label']]) }}"
								        aria-label="{{ __('Reset :label to default', ['label' => $slot['label']]) }}">
									<i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
								</button>
							</div>

							<div class="tm-swatch__control">
								<input type="color" class="tm-swatch__picker" id="{{ $fieldId }}"
								       value="{{ $slot['value'] }}" data-color-input
								       aria-label="{{ __(':label colour picker', ['label' => $slot['label']]) }}">
								<input type="text" class="tm-swatch__hex"
								       name="colors[{{ $slot['key'] }}]"
								       value="{{ $slot['value'] }}"
								       maxlength="7"
								       spellcheck="false"
								       autocomplete="off"
								       pattern="#[0-9A-Fa-f]{6}"
								       data-hex-input
								       aria-label="{{ __(':label hex value', ['label' => $slot['label']]) }}">
							</div>

							<span class="tm-swatch__hint">{{ $slot['hint'] }}</span>
							@error('colors.'.$slot['key'])
								<span class="tm-swatch__error">
									<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> {{ $message }}
								</span>
							@enderror
						</div>
					@endforeach
				</div>

				{{-- Live preview --}}
				@php($previewVars = collect($palette['slots'])->map(fn ($s) => '--c-'.$s['key'].': '.$s['value'])->implode('; '))
				<div class="tm-preview tm-preview--{{ $palette['value'] }}"
				     data-theme-preview="{{ $palette['value'] }}"
				     style="{{ $previewVars }}">
					<div class="tm-preview__head">
						<span class="tm-preview__label">
							<i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
							{{ __('Live preview') }}
						</span>
						<span class="tm-preview__hint">{{ __('Updates as you edit') }}</span>
					</div>
					<div class="tm-preview__stage">
						@if($palette['value'] === 'golden')
							<button type="button" class="tm-preview__btn-gold">{{ __('Open Account') }}</button>
							<span class="tm-preview__chip-gold">{{ __('Private Wealth') }}</span>
							<span class="tm-preview__gold-text">{{ __('24K') }}</span>
						@else
							<button type="button" class="tm-preview__btn">{{ __('Get Started') }}</button>
							<a href="#" class="tm-preview__link" onclick="return false;">{{ __('Learn more') }}</a>
							<span class="tm-preview__badge">{{ __('Verified') }}</span>
						@endif
					</div>
				</div>

				{{-- Footer: save + reset, grouped --}}
				<div class="tm-colors__footer">
					<p class="tm-colors__footer-note">
						<i class="fa-solid fa-circle-info" aria-hidden="true"></i>
						{{ __('Colours apply to the :theme landing pages once saved.', ['theme' => $palette['label']]) }}
					</p>
					<div class="tm-colors__footer-actions">
						<button type="submit"
						        form="tm-colors-reset-{{ $palette['value'] }}"
						        class="btn btn-outline-secondary tm-colors__reset">
							<i class="fa-solid fa-arrow-rotate-left me-1" aria-hidden="true"></i>{{ __('Reset to defaults') }}
						</button>
						<button type="submit" class="btn btn-primary tm-colors__save">
							<i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>{{ __('Save :theme palette', ['theme' => $palette['label']]) }}
						</button>
					</div>
				</div>
			</form>

			{{-- Empty reset form, driven by the footer button via form="…" --}}
			<form method="POST"
			      action="{{ route('admin.theme-manager.colors.reset', $palette['value']) }}"
			      id="tm-colors-reset-{{ $palette['value'] }}"
			      class="d-none">
				@csrf
			</form>
		</div>
	@endforeach
</section>
