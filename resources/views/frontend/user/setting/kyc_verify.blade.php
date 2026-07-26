@php
    use App\Enums\KycStatus;
    use App\Services\Kyc\Contracts\KycLiveVerifier;

    $kycSubmission = auth()->user()->kycSubmission;
    $kycStatus = $kycSubmission?->status ?? null;
    $kycLive = app(KycLiveVerifier::class);
    $kycLiveEnabled = $kycLive->isEnabled();
    $kycLiveSession = $kycLiveEnabled ? $kycLive->startSession(auth()->user()) : null;

    $liveMeta = is_array($kycSubmission?->submission_data['live_verification'] ?? null)
        ? $kycSubmission->submission_data['live_verification']
        : [];
    $awaitingDidit = kyc_submission_awaiting_didit($kycSubmission);

    $state = match (true) {
        $awaitingDidit => 'awaiting_didit',
        $kycStatus === KycStatus::PENDING => 'pending',
        $kycStatus === KycStatus::APPROVED => 'approved',
        $kycStatus === KycStatus::REJECTED => 'rejected',
        default => 'new',
    };

    $stateLabel = match ($state) {
        'awaiting_didit' => __('In Progress'),
        'pending' => __('Under Review'),
        'approved' => __('Verified'),
        'rejected' => __('Rejected'),
        default => __('Not Started'),
    };
    $stateIcon = match ($state) {
        'awaiting_didit' => 'fas fa-external-link-alt',
        'pending' => 'fas fa-hourglass-half',
        'approved' => 'fas fa-circle-check',
        'rejected' => 'fas fa-circle-exclamation',
        default => 'fas fa-id-card',
    };
    $stateTitle = match ($state) {
        'awaiting_didit' => __('Finish identity verification'),
        'pending' => __('KYC review in progress'),
        'approved' => __('KYC verification complete'),
        'rejected' => __('KYC needs attention'),
        default => __('Start identity verification'),
    };
    $stateSubtitle = match ($state) {
        'awaiting_didit' => __('You started verification but have not finished yet. Continue the check or cancel and start over.'),
        'pending' => __('Your KYC details are with our review team. We will update this page after the decision.'),
        'approved' => __('Your identity verification is complete. No further KYC action is required right now.'),
        'rejected' => __('Review the feedback, update your documents, and resubmit your identity details.'),
        default => __('Submit your identity details and documents to complete KYC verification.'),
    };

    // Pending Didit sessions are still editable/resumable until Didit returns a decision.
    $canSubmit = ! $kycSubmission || $kycStatus === KycStatus::REJECTED || $awaitingDidit;
    $submittedAt = $kycSubmission?->created_at;
    $reviewedAt = $kycSubmission?->updated_at?->gt($kycSubmission->created_at) ? $kycSubmission->updated_at : null;
@endphp
@extends('frontend.user.setting.index')
@section('title', __('KYC Verification'))

@section('user_setting_content')

    <section class="kyc-verify-hero kyc-verify-hero--{{ $state }} mb-4">
        <div class="kyc-verify-hero__icon" aria-hidden="true">
            <i class="{{ $stateIcon }}"></i>
        </div>
        <div class="kyc-verify-hero__copy">
            <span class="kyc-verify-hero__eyebrow">{{ __('Identity Verification') }}</span>
            <h5 class="kyc-verify-hero__title">{{ $stateTitle }}</h5>
            <p class="kyc-verify-hero__subtitle">{{ $stateSubtitle }}</p>
        </div>
        <span class="kyc-verify-hero__badge kyc-verify-hero__badge--{{ $state }}">
            <i class="{{ $stateIcon }}" aria-hidden="true"></i>
            {{ $stateLabel }}
        </span>

        <div class="kyc-verify-hero__meta">
            <div class="kyc-verify-hero__meta-item">
                <span>{{ __('Current Status') }}</span>
                <strong>{{ $stateLabel }}</strong>
            </div>
            <div class="kyc-verify-hero__meta-item">
                <span>{{ $awaitingDidit ? __('Started') : __('Submitted') }}</span>
                <strong>{{ $submittedAt ? $submittedAt->diffForHumans() : __('Not submitted') }}</strong>
            </div>
            <div class="kyc-verify-hero__meta-item">
                <span>{{ __('Last Update') }}</span>
                <strong>
                    @if($awaitingDidit)
                        {{ __('Waiting for you to finish') }}
                    @else
                        {{ $reviewedAt ? $reviewedAt->diffForHumans() : __('Awaiting review') }}
                    @endif
                </strong>
            </div>
        </div>
    </section>

    {{-- Status panel with submission timeline --}}
    @if($kycSubmission)
        <section class="kyc-verify-status kyc-verify-status--{{ $state }} kyc-verify-status--timeline mb-30" aria-live="polite">
            <header class="kyc-verify-timeline-head">
                <div>
                    <span class="kyc-verify-timeline-head__eyebrow">{{ __('Verification History') }}</span>
                    <h6 class="kyc-verify-timeline-head__title">{{ __('Progress') }}</h6>
                </div>
                <span class="kyc-verify-header__badge kyc-verify-header__badge--{{ $state }}">
                    <span class="kyc-verify-header__badge-dot" aria-hidden="true"></span>
                    {{ $stateLabel }}
                </span>
            </header>

            @if($awaitingDidit)
                <ol class="kyc-verify-timeline">
                    <li class="kyc-verify-timeline__step is-done">
                        <span class="kyc-verify-timeline__dot" aria-hidden="true"></span>
                        <div class="kyc-verify-timeline__body">
                            <span class="kyc-verify-timeline__label">{{ __('Details saved') }}</span>
                            <small class="kyc-verify-timeline__meta">{{ $kycSubmission->created_at?->diffForHumans() }}</small>
                        </div>
                    </li>
                    <li class="kyc-verify-timeline__step is-active">
                        <span class="kyc-verify-timeline__dot" aria-hidden="true"></span>
                        <div class="kyc-verify-timeline__body">
                            <span class="kyc-verify-timeline__label">{{ __('Identity check') }}</span>
                            <small class="kyc-verify-timeline__meta">{{ __('Not finished yet') }}</small>
                        </div>
                    </li>
                    <li class="kyc-verify-timeline__step">
                        <span class="kyc-verify-timeline__dot" aria-hidden="true"></span>
                        <div class="kyc-verify-timeline__body">
                            <span class="kyc-verify-timeline__label">{{ __('Decision') }}</span>
                            <small class="kyc-verify-timeline__meta">{{ __('Waiting') }}</small>
                        </div>
                    </li>
                </ol>
            @else
                <ol class="kyc-verify-timeline">
                    <li class="kyc-verify-timeline__step is-done">
                        <span class="kyc-verify-timeline__dot" aria-hidden="true"></span>
                        <div class="kyc-verify-timeline__body">
                            <span class="kyc-verify-timeline__label">{{ __('Submitted') }}</span>
                            <small class="kyc-verify-timeline__meta">{{ $kycSubmission->created_at?->diffForHumans() }}</small>
                        </div>
                    </li>
                    <li class="kyc-verify-timeline__step {{ $state === 'pending' ? 'is-active' : 'is-done' }}">
                        <span class="kyc-verify-timeline__dot" aria-hidden="true"></span>
                        <div class="kyc-verify-timeline__body">
                            <span class="kyc-verify-timeline__label">{{ __('Under Review') }}</span>
                            <small class="kyc-verify-timeline__meta">
                                {{ $state === 'pending' ? __('In progress') : __('Completed') }}
                            </small>
                        </div>
                    </li>
                    <li class="kyc-verify-timeline__step {{ in_array($state, ['approved', 'rejected'], true) ? 'is-done is-' . $state : '' }}">
                        <span class="kyc-verify-timeline__dot" aria-hidden="true"></span>
                        <div class="kyc-verify-timeline__body">
                            <span class="kyc-verify-timeline__label">
                                @switch($state)
                                    @case('approved') {{ __('Approved') }} @break
                                    @case('rejected') {{ __('Rejected') }} @break
                                    @default {{ __('Decision') }}
                                @endswitch
                            </span>
                            <small class="kyc-verify-timeline__meta">
                                {{ $reviewedAt ? $reviewedAt->diffForHumans() : __('Awaiting') }}
                            </small>
                        </div>
                    </li>
                </ol>
            @endif
        </section>
    @endif

    @if($awaitingDidit || $state === 'pending')
        <section class="kyc-verify-form-card mb-30">
            <div class="kyc-live kyc-live--didit">
                <div class="kyc-live__header">
                    <div>
                        <span class="kyc-live__eyebrow">
                            {{ $awaitingDidit ? __('Almost there') : __('Submission pending') }}
                        </span>
                        <h6 class="kyc-live__title">
                            {{ $awaitingDidit ? __('Complete your identity check') : __('Not finished yet') }}
                        </h6>
                        <p class="kyc-live__subtitle">
                            @if($awaitingDidit)
                                {{ __('Finish the ID and face steps to complete verification, or cancel and start over.') }}
                            @else
                                {{ __('If you have not finished, cancel and start again.') }}
                            @endif
                        </p>
                    </div>
                    <span class="kyc-live__badge">{{ $awaitingDidit ? __('In progress') : __('Pending') }}</span>
                </div>
                <div class="kyc-live__actions d-flex flex-wrap gap-2 mt-3">
                    @if($awaitingDidit)
                        <form method="POST" action="{{ route('user.settings.kyc.didit.resume') }}" class="flex-grow-1">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-external-link-alt me-2" aria-hidden="true"></i>
                                {{ __('Continue verification') }}
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('user.settings.kyc.didit.cancel') }}" @class(['flex-grow-1' => ! $awaitingDidit])>
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary w-100"
                                onclick="return confirm(@json(__('Cancel this KYC attempt and start over?')))">
                            {{ __('Cancel & Restart') }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    {{-- Submission form --}}
    @if($canSubmit && ! $awaitingDidit)
        <section class="kyc-verify-form-card">
            <header class="kyc-verify-form-card__header">
                <div class="kyc-verify-form-card__heading">
                    <h6 class="kyc-verify-form-card__title">
                        {{ $kycStatus === KycStatus::REJECTED ? __('Resubmit your verification') : __('Start your verification') }}
                    </h6>
                    <p class="kyc-verify-form-card__subtitle">
                        {{ __('Provide accurate information to avoid delays in review.') }}
                    </p>
                </div>
                <span class="kyc-verify-form-card__hint">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    {{ __('Your data is encrypted and private.') }}
                </span>
            </header>

            <form id="kyc-verify-form"
                  action="{{ ($kycLiveSession['mode'] ?? null) === 'redirect' ? route('user.settings.kyc.didit.start') : route('user.settings.kyc.submit') }}"
                  method="POST"
                  enctype="multipart/form-data" class="kyc-verify-form-card__body">
                @csrf

                <div class="kyc-verify-field">
                    <label for="template-select" class="kyc-verify-field__label">
                        <span class="kyc-verify-field__step">1</span>
                        {{ __('Verification Type') }}
                    </label>
                    <p class="kyc-verify-field__hint">
                        {{ __('Choose the document type that matches what you have on hand.') }}
                    </p>
                    <div class="single-select-inner style-border">
                        <select class="form-select" name="template_id" id="template-select" required>
                            <option disabled selected>{{ __('Select Type') }}</option>
                            @foreach($kycTemplates as $kycTemplate)
                                <option value="{{ $kycTemplate->id }}">{{ $kycTemplate->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="kyc-verify-field">
                    <label class="kyc-verify-field__label">
                        <span class="kyc-verify-field__step">2</span>
                        {{ __('Required Information') }}
                    </label>
                    <p class="kyc-verify-field__hint">
                        {{ __('Fill in every required field and upload clear, legible documents.') }}
                    </p>
                    <div id="template-details" class="kyc-verify-template-details">
                        <div class="kyc-verify-template-empty" data-kyc-empty>
                            <i class="fas fa-file-lines" aria-hidden="true"></i>
                            <span>{{ __('Select a verification type above to load the required fields.') }}</span>
                        </div>
                    </div>
                </div>

                @if($kycLiveEnabled && ($kycLiveSession['mode'] ?? null) === 'redirect')
                    <div class="kyc-verify-field">
                        <label class="kyc-verify-field__label">
                            <span class="kyc-verify-field__step">3</span>
                            {{ __('Live Verification') }}
                        </label>
                        <p class="kyc-verify-field__hint">
                            {{ __('Fill the fields above, then continue to complete document and face verification. Results update automatically.') }}
                        </p>
                        <div class="kyc-live kyc-live--didit">
                            <div class="kyc-live__header">
                                <div>
                                    <span class="kyc-live__eyebrow">{{ __('Hosted verification') }}</span>
                                    <h6 class="kyc-live__title">{{ __('Live identity check') }}</h6>
                                    <p class="kyc-live__subtitle">
                                        {{ __('You will leave this page briefly for the secure verification flow, then return here when finished.') }}
                                    </p>
                                </div>
                                <span class="kyc-live__badge">
                                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                                    {{ __('Secure') }}
                                </span>
                            </div>
                            <div class="kyc-live__actions mt-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100" data-didit-start>
                                    <i class="fas fa-external-link-alt me-2" aria-hidden="true"></i>
                                    {{ __('Start live verification') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                @if($kycLiveEnabled && ($kycLiveSession['mode'] ?? null) === 'camera')
                    <div class="kyc-verify-field">
                        <label class="kyc-verify-field__label">
                            <span class="kyc-verify-field__step">3</span>
                            {{ __('Live Verification') }}
                        </label>
                        <p class="kyc-verify-field__hint">
                            {{ __('Capture your document photo and a :seconds-second live video. File upload above still works if the camera is unavailable.', ['seconds' => (int) config('kyc.builtin.liveness_record_seconds', 5)]) }}
                        </p>

                        <div class="kyc-live"
                             data-kyc-live
                             data-require-selfie="{{ ! empty($kycLiveSession['require_selfie']) ? '1' : '0' }}"
                             data-require-document="{{ ! empty($kycLiveSession['require_document']) ? '1' : '0' }}"
                             data-record-seconds="{{ config('kyc.builtin.liveness_record_seconds', 5) }}">
                            <div class="kyc-live__header">
                                <div>
                                    <span class="kyc-live__eyebrow">{{ __('Camera capture') }}</span>
                                    <h6 class="kyc-live__title">{{ __('Document & live video') }}</h6>
                                    <p class="kyc-live__subtitle">
                                        {{ __('Hold your document steady, then record a short live video of your face.') }}
                                    </p>
                                </div>
                                <span class="kyc-live__badge">
                                    <i class="fas fa-video" aria-hidden="true"></i>
                                    {{ __('Builtin') }}
                                </span>
                            </div>

                            <div class="kyc-live__steps">
                                <div class="kyc-live__card kyc-live__card--doc">
                                    <h6 class="kyc-live__card-title">{{ __('1. Document photo') }}</h6>
                                    <p class="kyc-live__card-text">
                                        {{ __('Use the rear camera when available. Align the full document in the frame.') }}
                                    </p>
                                    <img class="kyc-live__preview" data-kyc-live-doc-preview alt="{{ __('Document preview') }}" hidden>
                                    <span class="kyc-live__ready" data-kyc-live-doc-ready hidden>
                                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                                        {{ __('Document captured') }}
                                    </span>
                                    <div class="kyc-live__actions">
                                        <button type="button" class="btn btn-sm btn-primary" data-kyc-live-start="document">
                                            <i class="fas fa-camera" aria-hidden="true"></i>
                                            {{ __('Use camera') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-kyc-live-retake-doc hidden>
                                            {{ __('Retake') }}
                                        </button>
                                    </div>
                                    <input type="file"
                                           name="credentials[live_document]"
                                           accept="image/jpeg,image/png,image/webp"
                                           data-kyc-live-doc-input
                                           class="d-none"
                                           tabindex="-1"
                                           aria-hidden="true">
                                </div>

                                <div class="kyc-live__card kyc-live__card--selfie">
                                    <h6 class="kyc-live__card-title">
                                        {{ __('2. Live video') }}
                                        @if(! empty($kycLiveSession['require_selfie']))
                                            <span class="text-danger" aria-hidden="true">*</span>
                                        @endif
                                    </h6>
                                    <p class="kyc-live__card-text">
                                        {{ __('Record a :seconds-second live video with your face clearly visible.', ['seconds' => (int) config('kyc.builtin.liveness_record_seconds', 5)]) }}
                                    </p>
                                    <video class="kyc-live__preview kyc-live__preview--video"
                                           data-kyc-live-selfie-preview
                                           playsinline
                                           muted
                                           loop
                                           controls
                                           hidden></video>
                                    <span class="kyc-live__ready" data-kyc-live-selfie-ready hidden>
                                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                                        {{ __('Live video recorded') }}
                                    </span>
                                    <div class="kyc-live__actions">
                                        <button type="button" class="btn btn-sm btn-primary" data-kyc-live-start="selfie">
                                            <i class="fas fa-video" aria-hidden="true"></i>
                                            {{ __('Record video') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-kyc-live-retake-selfie hidden>
                                            {{ __('Retake') }}
                                        </button>
                                    </div>
                                    <input type="file"
                                           name="credentials[live_selfie]"
                                           accept="video/webm,video/mp4,video/*"
                                           data-kyc-live-selfie-input
                                           class="d-none"
                                           tabindex="-1"
                                           aria-hidden="true"
                                           @if(! empty($kycLiveSession['require_selfie'])) required @endif>
                                </div>
                            </div>

                            <div class="kyc-live__error" data-kyc-live-error hidden role="alert"></div>

                            <div class="kyc-live__stage" data-kyc-live-stage hidden>
                                <div class="kyc-live__video-wrap">
                                    <video class="kyc-live__video" data-kyc-live-video playsinline muted autoplay></video>
                                    <div class="kyc-live__overlay" aria-hidden="true"></div>
                                    <canvas data-kyc-live-canvas class="d-none" aria-hidden="true"></canvas>
                                </div>
                                <div class="kyc-live__stage-bar">
                                    <div>
                                        <p class="kyc-live__hint" data-kyc-live-hint></p>
                                        <div class="kyc-live__progress" data-kyc-live-progress hidden>
                                            <div class="kyc-live__progress-bar" data-kyc-live-progress-bar></div>
                                        </div>
                                    </div>
                                    <div class="kyc-live__stage-actions">
                                        <button type="button" class="btn btn-sm btn-light" data-kyc-live-cancel>
                                            {{ __('Cancel') }}
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" data-kyc-live-snap>
                                            {{ __('Capture') }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <p class="kyc-live__fallback">
                                {{ __('Camera needs HTTPS or localhost. If permission is denied, upload a file in the fields above.') }}
                            </p>
                        </div>
                    </div>
                @endif

                <div class="kyc-verify-field">
                    <label for="kyc-note" class="kyc-verify-field__label">
                        <span class="kyc-verify-field__step">{{ $kycLiveEnabled ? '4' : '3' }}</span>
                        {{ __('Additional Notes') }}
                        <span class="kyc-verify-field__optional">{{ __('Optional') }}</span>
                    </label>
                    <p class="kyc-verify-field__hint">
                        {{ __('Anything the reviewer should know about your submission.') }}
                    </p>
                    <div class="single-input-inner style-border">
                        <textarea class="rounded" name="note" id="kyc-note" rows="4"
                                  placeholder="{{ __('e.g. name on document differs slightly due to spelling.') }}"></textarea>
                    </div>
                </div>

                <footer class="kyc-verify-form-card__footer">
                    <p class="kyc-verify-form-card__legal">
                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                        {{ __('By submitting, you confirm the information provided is accurate and belongs to you.') }}
                    </p>
                    <button type="submit" class="btn btn-primary kyc-verify-form-card__submit">
                        <i class="fas fa-shield-alt" aria-hidden="true"></i>
                        <span>
                            @if(($kycLiveSession['mode'] ?? null) === 'redirect')
                                {{ __('Continue verification') }}
                            @else
                                {{ __('Submit for Review') }}
                            @endif
                        </span>
                    </button>
                </footer>
            </form>
        </section>
    @endif
@endsection

@push('styles')
    @if($kycLiveEnabled && ($kycLiveSession['mode'] ?? null) === 'camera')
        <link rel="stylesheet" href="{{ asset('frontend/css/kyc-live.css') }}?v={{ config('app.version') }}">
    @elseif($kycLiveEnabled && ($kycLiveSession['mode'] ?? null) === 'redirect')
        <link rel="stylesheet" href="{{ asset('frontend/css/kyc-live.css') }}?v={{ config('app.version') }}">
    @endif
@endpush

@push('scripts')
    @if($kycLiveEnabled && ($kycLiveSession['mode'] ?? null) === 'camera')
        <script src="{{ asset('frontend/js/kyc-live-capture.js') }}?v={{ config('app.version') }}"></script>
    @endif
    <script>
        "use strict";

        (function () {
            const $select  = $('#template-select');
            const $details = $('#template-details');
            const urlTemplate = @json(route('user.settings.kyc.template.details', ':id'));
            const liveEnabled = @json($kycLiveEnabled);

            const emptyState = `
                <div class="kyc-verify-template-empty" data-kyc-empty>
                    <i class="fas fa-file-lines" aria-hidden="true"></i>
                    <span>{{ __('Select a verification type above to load the required fields.') }}</span>
                </div>
            `;

            const loadingState = `
                <div class="kyc-verify-template-loading">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">{{ __('Loading...') }}</span>
                    </div>
                    <span>{{ __('Loading required fields...') }}</span>
                </div>
            `;

            function afterTemplateLoaded() {
                if (liveEnabled && window.DigiKashKycLive) {
                    window.DigiKashKycLive.bootAll(document);
                }
                // Show retake buttons when previews become visible
                document.querySelectorAll('[data-kyc-live]').forEach(function (root) {
                    const retakeDoc = root.querySelector('[data-kyc-live-retake-doc]');
                    const retakeSelfie = root.querySelector('[data-kyc-live-retake-selfie]');
                    const docReady = root.querySelector('[data-kyc-live-doc-ready]');
                    const selfieReady = root.querySelector('[data-kyc-live-selfie-ready]');
                    if (retakeDoc && docReady) {
                        const obs = new MutationObserver(function () {
                            retakeDoc.hidden = docReady.hidden;
                        });
                        obs.observe(docReady, { attributes: true, attributeFilter: ['hidden'] });
                    }
                    if (retakeSelfie && selfieReady) {
                        const obs = new MutationObserver(function () {
                            retakeSelfie.hidden = selfieReady.hidden;
                        });
                        obs.observe(selfieReady, { attributes: true, attributeFilter: ['hidden'] });
                    }
                });
            }

            $select.on('change', function () {
                const templateId = $(this).val();

                if (!templateId) {
                    $details.html(emptyState);
                    return;
                }

                $details.html(loadingState);

                $.get(urlTemplate.replace(':id', templateId))
                    .done(response => {
                        $details.html(response);
                        afterTemplateLoaded();
                    })
                    .fail(() => {
                        $details.html(`
                            <div class="kyc-verify-template-empty kyc-verify-template-empty--error">
                                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                                <span>{{ __('Unable to load fields. Please try again.') }}</span>
                            </div>
                        `);
                    });
            });

            afterTemplateLoaded();
        })();
    </script>
@endpush
