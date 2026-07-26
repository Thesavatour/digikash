@props([
    'value' => null,
    'placeholder' => 'Ex: natcash-htg',
    'context' => 'deposit',
])

@php
    $methodExample = \Illuminate\Support\Str::of($placeholder)->after('Ex: ')->trim()->value() ?: 'natcash-htg';
@endphp

<div class="method-code-panel">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
        <label class="form-label mb-0" for="method_code">{{ __('Method Code') }}</label>
        <span class="badge method-code-panel__badge">
            <i class="fa-solid fa-fingerprint me-1"></i>{{ __('Unique') }}
        </span>
    </div>
    <div class="input-group method-code-panel__input">
        <span class="input-group-text" aria-hidden="true">
            <i class="fa-solid fa-code"></i>
        </span>
        <input id="method_code"
               class="form-control @error('method_code') is-invalid @enderror"
               type="text"
               name="method_code"
               value="{{ old('method_code', $value) }}"
               placeholder="{{ __($placeholder) }}"
               autocomplete="off"
               required
               data-method-code
               aria-describedby="method-code-help">
        <button type="button"
                class="btn method-code-panel__generate"
                data-generate-method-code
                data-context="{{ $context }}"
                title="{{ __('Generate a unique code') }}">
            <i class="fa-solid fa-wand-magic me-1"></i>{{ __('Generate') }}
        </button>
    </div>
    <div id="method-code-help" class="method-code-panel__help">
        <span class="method-code-panel__dot" aria-hidden="true"></span>
        <span>
            {{ __('Unique :context code — lowercase, numbers & hyphens (e.g. :example). Tap Generate or type your own.', [
                'context' => __($context),
                'example' => $methodExample,
            ]) }}
        </span>
    </div>
    @error('method_code')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>
