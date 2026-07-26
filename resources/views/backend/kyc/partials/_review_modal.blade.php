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
                    </div>

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
                            @foreach($submission->submission_data as $fieldName => $value)
                                @php
                                    $isFile = is_string($value) && \Illuminate\Support\Str::startsWith($value, 'files/');
                                    $isImage = $isFile && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value);
                                @endphp
                                <div class="kyc-modal__field">
                                    <span class="kyc-modal__field-label">{{ ucwords(str_replace(['_', '-'], ' ', $fieldName)) }}</span>

                                    @if($isImage)
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
