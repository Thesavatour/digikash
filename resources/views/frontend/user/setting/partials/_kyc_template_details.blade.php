{{--
    KYC template field group partial.
    Rendered inside the parent verification form; must NOT contain a nested <form>.
--}}
@php
    $kycLive = app(\App\Services\Kyc\Contracts\KycLiveVerifier::class);
    $liveEnabled = $kycLive->isEnabled();
@endphp
<div class="kyc-template-fields">
    <header class="kyc-template-fields__header">
        <h6 class="kyc-template-fields__title">{{ $template->title }}</h6>
        @if(! empty($template->description))
            <p class="kyc-template-fields__description">{{ $template->description }}</p>
        @endif
    </header>

    <div class="row g-3 kyc-template-fields__grid">
        @foreach($template->fields as $field)
            @php
                $fieldLabel    = ucfirst(str_replace('_', ' ', $field['label']));
                $fieldKey      = $field['label'];
                $fieldType     = $field['type'] ?? 'text';
                // Templates store required as boolean|string "true"; older seeds used "validation".
                $isRequired    = (isset($field['required']) && ($field['required'] === true || $field['required'] === 'true'))
                    || ! empty($field['validation']);
                $fieldId       = 'kyc-credential-' . \Illuminate\Support\Str::slug($fieldKey);
                $fieldName     = "credentials[{$fieldKey}]";
            @endphp

            <div class="col-md-6 kyc-template-fields__item">
                <label for="{{ $fieldId }}" class="form-label">
                    {{ $fieldLabel }}
                    @if($isRequired)
                        <span class="text-danger" aria-hidden="true">*</span>
                    @endif
                </label>

                @if($fieldType === 'file')
                    <input type="file"
                           id="{{ $fieldId }}"
                           name="{{ $fieldName }}"
                           class="form-control"
                           accept="image/*,.pdf"
                           @if($isRequired) required @endif>
                    @if($liveEnabled)
                        <p class="kyc-verify-field__hint mt-1 mb-0">
                            {{ __('Or use the Live verification camera below to capture this document.') }}
                        </p>
                    @endif
                @elseif($fieldType === 'textarea')
                    <textarea id="{{ $fieldId }}"
                              name="{{ $fieldName }}"
                              class="form-control rounded"
                              rows="3"
                              placeholder="{{ $fieldLabel }}"
                              @if($isRequired) required @endif></textarea>
                @else
                    <input type="{{ $fieldType }}"
                           id="{{ $fieldId }}"
                           name="{{ $fieldName }}"
                           class="form-control"
                           placeholder="{{ $fieldLabel }}"
                           @if($isRequired) required @endif>
                @endif
            </div>
        @endforeach
    </div>
</div>
