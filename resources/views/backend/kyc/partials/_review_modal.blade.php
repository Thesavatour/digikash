{{--
    KYC review / approval modal.

    Premium redesign mirroring the merchant review modal pattern:
    - Gradient header with avatar, name, email, status pill, close button
    - Tinted callout summarising the review state
    - Meta grid for KYC type, submitted-at, user role
    - Submitted fields rendered as proper field cards with file previews
    - Optional remarks textarea (only when action !== 'view')
    - Premium action buttons (Approve / Reject) with the shared styles
--}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('backend/css/kyc-review-modal.css?v='.config('app.version').'-'.filemtime(public_path('backend/css/kyc-review-modal.css'))) }}">
    @endpush
@endonce

@php
    $user = $submission->user;
    $avatarData = getUserAvatarDetails($user->first_name, $user->last_name);
    $statusEnum = $submission->status;
    $statusTone = match($statusEnum?->value) {
        \App\Enums\KycStatus::APPROVED->value => 'is-approved',
        \App\Enums\KycStatus::PENDING->value  => 'is-pending',
        \App\Enums\KycStatus::REJECTED->value => 'is-rejected',
        default                                => '',
    };
    $statusLabel = $statusEnum?->label() ?? __('Unknown');
    $kycType = $submission->kycTemplate?->title ?? __('Unknown template');
    $reviewMode = empty($action) || $action !== 'view';
    $hasRemarks = filled($submission->notes ?? null);
    $submissionData = is_array($submission->submission_data) ? $submission->submission_data : [];
    $liveMeta = is_array($submissionData['live_verification'] ?? null) ? $submissionData['live_verification'] : null;
    $liveSelfie = is_string($submissionData['live_selfie'] ?? null) ? $submissionData['live_selfie'] : null;
    $liveDocument = is_string($submissionData['live_document'] ?? null) ? $submissionData['live_document'] : null;
    $liveDriver = is_string($liveMeta['driver'] ?? null) ? $liveMeta['driver'] : null;
    $hasLiveEvidence = filled($liveSelfie) || filled($liveDocument) || filled($liveDriver);
    $liveSelfieIsVideo = is_string($liveSelfie) && preg_match('/\.(webm|mp4|mov)$/i', $liveSelfie);
@endphp

<div class="modal fade kyc-modal" id="review-{{ $submission->id }}" tabindex="-1" aria-labelledby="KycReviewLabel-{{ $submission->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content kyc-modal__content">

            {{-- Header --}}
            <div class="modal-header kyc-modal__header">
                <div class="kyc-modal__brand">
                    @if($user->avatar)
                        <img src="{{ asset($user->avatar) }}"
                             alt="{{ $user->name }}"
                             class="kyc-modal__avatar"
                             loading="lazy">
                    @else
                        <div class="kyc-modal__avatar kyc-modal__avatar--initials {{ $avatarData['class'] }}">
                            {{ $avatarData['initials'] }}
                        </div>
                    @endif
                    <div class="kyc-modal__brand-text">
                        <h5 class="kyc-modal__title" id="KycReviewLabel-{{ $submission->id }}">{{ title($user->name) }}</h5>
                        <span class="kyc-modal__subtitle">{{ $user->email }}</span>
                    </div>
                </div>

                <span class="kyc-modal__status {{ $statusTone }}">
                    <span class="kyc-modal__status-dot" aria-hidden="true"></span>
                    {{ $statusLabel }}
                </span>

                <button type="button"
                        class="kyc-modal__close"
                        data-coreui-dismiss="modal"
                        aria-label="{{ __('Close') }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Action form --}}
            <form action="{{ route('admin.kyc.request-action') }}" method="post">
                @csrf
                <input type="hidden" name="submission_id" value="{{ $submission->id }}">

                {{-- Body --}}
                <div class="modal-body kyc-modal__body">

                    {{-- Status callout --}}
                    @if($statusEnum === \App\Enums\KycStatus::APPROVED)
                        <div class="kyc-modal__callout kyc-modal__callout--success" role="status">
                            <span class="kyc-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <div>{{ __('This KYC submission has been approved.') }}</div>
                        </div>
                    @elseif($statusEnum === \App\Enums\KycStatus::PENDING)
                        <div class="kyc-modal__callout kyc-modal__callout--info" role="status">
                            <span class="kyc-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </span>
                            <div>{{ __('Review the documents below. Approving will mark the user as KYC-verified; rejecting lets them resubmit.') }}</div>
                        </div>
                    @elseif($statusEnum === \App\Enums\KycStatus::REJECTED)
                        <div class="kyc-modal__callout kyc-modal__callout--danger" role="status">
                            <span class="kyc-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </span>
                            <div>{{ __('This KYC submission was rejected. The user can resubmit a new one.') }}</div>
                        </div>
                    @endif

                    {{-- Meta grid --}}
                    <div class="kyc-modal__meta">
                        <div class="kyc-modal__meta-item">
                            <span class="kyc-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-id-card"></i>
                            </span>
                            <div class="kyc-modal__meta-body">
                                <span class="kyc-modal__meta-label">{{ __('KYC Type') }}</span>
                                <span class="kyc-modal__meta-value" title="{{ $kycType }}">{{ $kycType }}</span>
                            </div>
                        </div>

                        <div class="kyc-modal__meta-item">
                            <span class="kyc-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-user-tag"></i>
                            </span>
                            <div class="kyc-modal__meta-body">
                                <span class="kyc-modal__meta-label">{{ __('User Role') }}</span>
                                <span class="kyc-modal__meta-value">
                                    <span class="admin-user-role-badge admin-user-role-badge--{{ $user->role->value }}">
                                        {{ $user->role->title() }}
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="kyc-modal__meta-item">
                            <span class="kyc-modal__meta-icon" aria-hidden="true">
                                <i class="fa-regular fa-calendar"></i>
                            </span>
                            <div class="kyc-modal__meta-body">
                                <span class="kyc-modal__meta-label">{{ __('Submitted') }}</span>
                                <span class="kyc-modal__meta-value">
                                    {{ $submission->created_at->format('Y-m-d H:i') }}
                                </span>
                            </div>
                        </div>

                        <div class="kyc-modal__meta-item">
                            <span class="kyc-modal__meta-icon" aria-hidden="true">
                                <i class="fa-regular fa-clock"></i>
                            </span>
                            <div class="kyc-modal__meta-body">
                                <span class="kyc-modal__meta-label">{{ __('Relative') }}</span>
                                <span class="kyc-modal__meta-value">{{ $submission->created_at->diffForHumans() }}</span>
                            </div>
                        </div>

                        @if($hasLiveEvidence)
                            <div class="kyc-modal__meta-item">
                                <span class="kyc-modal__meta-icon" aria-hidden="true">
                                    <i class="fa-solid fa-video"></i>
                                </span>
                                <div class="kyc-modal__meta-body">
                                    <span class="kyc-modal__meta-label">{{ __('Live Driver') }}</span>
                                    <span class="kyc-modal__meta-value">
                                        <span class="badge text-bg-primary text-uppercase">{{ $liveDriver ?: __('n/a') }}</span>
                                    </span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Live camera evidence --}}
                    @if($hasLiveEvidence)
                        <section class="kyc-modal__section" aria-labelledby="kyc-live-{{ $submission->id }}">
                            <header class="kyc-modal__section-head">
                                <span class="kyc-modal__section-icon" aria-hidden="true">
                                    <i class="fa-solid fa-camera"></i>
                                </span>
                                <h3 class="kyc-modal__section-title" id="kyc-live-{{ $submission->id }}">
                                    {{ __('Live verification captures') }}
                                    @if($liveDriver)
                                        <span class="kyc-modal__section-hint">{{ __('Driver') }}: {{ $liveDriver }}</span>
                                    @endif
                                </h3>
                            </header>

                            @if(! empty($liveMeta['captured_at']))
                                <p class="small text-body-secondary mb-3">
                                    {{ __('Captured') }}:
                                    {{ \Illuminate\Support\Carbon::parse($liveMeta['captured_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </p>
                            @endif

                            <div class="kyc-modal__fields">
                                @if($liveSelfie)
                                    <div class="kyc-modal__field">
                                        <span class="kyc-modal__field-label">{{ __('Live video') }}</span>
                                        @if($liveSelfieIsVideo)
                                            <div class="kyc-modal__field-video">
                                                <video src="{{ asset($liveSelfie) }}"
                                                       controls
                                                       playsinline
                                                       preload="metadata"
                                                       style="width:100%;max-height:320px;border-radius:12px;background:#0b1220;">
                                                    {{ __('Your browser does not support video playback.') }}
                                                </video>
                                                <a href="{{ asset($liveSelfie) }}" target="_blank" rel="noopener" class="small d-inline-block mt-2">
                                                    {{ __('Open / download video') }}
                                                </a>
                                            </div>
                                        @else
                                            <a href="{{ asset($liveSelfie) }}" target="_blank" rel="noopener" class="kyc-modal__field-image">
                                                <img src="{{ asset($liveSelfie) }}" alt="{{ __('Live video') }}" loading="lazy">
                                                <span class="kyc-modal__field-image-overlay" aria-hidden="true">
                                                    <i class="fa-solid fa-up-right-from-square"></i>
                                                    {{ __('Open full size') }}
                                                </span>
                                            </a>
                                        @endif
                                    </div>
                                @endif

                                @if($liveDocument)
                                    <div class="kyc-modal__field">
                                        <span class="kyc-modal__field-label">{{ __('Live document') }}</span>
                                        <a href="{{ asset($liveDocument) }}" target="_blank" rel="noopener" class="kyc-modal__field-image">
                                            <img src="{{ asset($liveDocument) }}" alt="{{ __('Live document') }}" loading="lazy">
                                            <span class="kyc-modal__field-image-overlay" aria-hidden="true">
                                                <i class="fa-solid fa-up-right-from-square"></i>
                                                {{ __('Open full size') }}
                                            </span>
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </section>
                    @endif

                    {{-- Submitted note from user --}}
                    @if($hasRemarks)
                        <section class="kyc-modal__section" aria-labelledby="kyc-note-{{ $submission->id }}">
                            <header class="kyc-modal__section-head">
                                <span class="kyc-modal__section-icon" aria-hidden="true">
                                    <i class="fa-regular fa-comment"></i>
                                </span>
                                <h3 class="kyc-modal__section-title" id="kyc-note-{{ $submission->id }}">{{ __('User Note') }}</h3>
                            </header>
                            <div class="kyc-modal__quote">
                                {!! nl2br(e($submission->notes)) !!}
                            </div>
                        </section>
                    @endif

                    {{-- Submitted fields --}}
                    <section class="kyc-modal__section" aria-labelledby="kyc-fields-{{ $submission->id }}">
                        <header class="kyc-modal__section-head">
                            <span class="kyc-modal__section-icon" aria-hidden="true">
                                <i class="fa-regular fa-folder-open"></i>
                            </span>
                            <h3 class="kyc-modal__section-title" id="kyc-fields-{{ $submission->id }}">
                                {{ __('Submitted information') }}
                            </h3>
                        </header>

                        <div class="kyc-modal__fields">
                            @foreach($submissionData as $fieldName => $value)
                                @continue(in_array($fieldName, ['live_selfie', 'live_document', 'live_verification'], true))
                                @php
                                    $isFile = is_string($value) && \Illuminate\Support\Str::startsWith($value, 'files/');
                                    $isImage = $isFile && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value);
                                    $isVideo = $isFile && preg_match('/\.(webm|mp4|mov)$/i', $value);
                                @endphp
                                <div class="kyc-modal__field">
                                    <span class="kyc-modal__field-label">{{ ucwords(str_replace(['_', '-'], ' ', $fieldName)) }}</span>

                                    @if($isVideo)
                                        <video src="{{ asset($value) }}"
                                               controls
                                               playsinline
                                               preload="metadata"
                                               style="width:100%;max-height:280px;border-radius:12px;background:#0b1220;"></video>
                                    @elseif($isImage)
                                        <a href="{{ asset($value) }}" target="_blank" rel="noopener" class="kyc-modal__field-image">
                                            <img src="{{ asset($value) }}" alt="{{ $fieldName }}" loading="lazy">
                                            <span class="kyc-modal__field-image-overlay" aria-hidden="true">
                                                <i class="fa-solid fa-up-right-from-square"></i>
                                                {{ __('Open full size') }}
                                            </span>
                                        </a>
                                    @elseif($isFile)
                                        <a href="{{ asset($value) }}" download class="kyc-modal__field-file">
                                            <span class="kyc-modal__field-file-icon" aria-hidden="true">
                                                <i class="fa-regular fa-file-lines"></i>
                                            </span>
                                            <span class="kyc-modal__field-file-body">
                                                <strong>{{ basename($value) }}</strong>
                                                <small>{{ __('Download attachment') }}</small>
                                            </span>
                                            <i class="fa-solid fa-download kyc-modal__field-file-action" aria-hidden="true"></i>
                                        </a>
                                    @elseif(is_array($value))
                                        <span class="kyc-modal__field-value"><code>{{ json_encode($value) }}</code></span>
                                    @else
                                        <span class="kyc-modal__field-value">{{ $value !== null && $value !== '' ? $value : __('—') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Admin remarks (only in action mode) --}}
                    @if($reviewMode)
                        <section class="kyc-modal__section" aria-labelledby="kyc-remarks-{{ $submission->id }}">
                            <header class="kyc-modal__section-head">
                                <span class="kyc-modal__section-icon" aria-hidden="true">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </span>
                                <h3 class="kyc-modal__section-title" id="kyc-remarks-{{ $submission->id }}">
                                    {{ __('Add your remarks') }}
                                    <span class="kyc-modal__section-hint">{{ __('Optional — shared with the user') }}</span>
                                </h3>
                            </header>
                            <textarea class="kyc-modal__textarea"
                                      name="remarks"
                                      id="remarks-{{ $submission->id }}"
                                      rows="3"
                                      placeholder="{{ __('Write your remarks here…') }}"></textarea>
                        </section>
                    @endif
                </div>

                {{-- Footer --}}
                @if($reviewMode)
                    <div class="modal-footer kyc-modal__footer">
                        <button type="submit"
                                name="action"
                                value="approve"
                                class="kyc-modal__btn kyc-modal__btn--approve"
                                title="{{ __('Approve this KYC submission') }}">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            <span>{{ __('Approve KYC') }}</span>
                        </button>
                        <button type="submit"
                                name="action"
                                value="reject"
                                class="kyc-modal__btn kyc-modal__btn--reject"
                                title="{{ __('Reject this KYC submission') }}">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            <span>{{ __('Reject KYC') }}</span>
                        </button>
                    </div>
                @else
                    <div class="modal-footer kyc-modal__footer">
                        <div class="kyc-modal__footer-empty">
                            {{ __('Read-only view. Switch to the pending queue to take action.') }}
                        </div>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
