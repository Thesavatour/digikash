{{--
    Merchant review / approval modal.

    Markup styled exclusively via `public/backend/css/merchant-review-modal.css`
    (no inline styles, no Bootstrap utility scaffolding for visuals — only
    for the modal frame and the form binding). All decisions live in CSS
    tokens that follow the rest of the admin theme.
--}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('backend/css/merchant-review-modal.css?v='.config('app.version').'-'.filemtime(public_path('backend/css/merchant-review-modal.css'))) }}">
    @endpush
@endonce

@php
    $statusToneMap = [
        \App\Enums\MerchantStatus::PENDING->value  => 'is-pending',
        \App\Enums\MerchantStatus::APPROVED->value => 'is-approved',
        \App\Enums\MerchantStatus::DISABLED->value => 'is-disabled',
        \App\Enums\MerchantStatus::REJECTED->value => 'is-rejected',
    ];
    $statusToneClass = $statusToneMap[$merchant->status->value] ?? '';
    $description     = trim((string) ($merchant->business_description ?? ''));
    $descriptionId   = 'mr-modal-desc-'.$merchant->id;
    $currencies      = $merchant->supportedCurrencies->pluck('code')->implode(', ') ?: $merchant->currency->code;
@endphp

<div class="modal fade mr-modal" id="review-{{ $merchant->id }}" tabindex="-1" aria-labelledby="MerchantDetailsLabel-{{ $merchant->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content mr-modal__content">
            {{-- Header --}}
            <div class="modal-header mr-modal__header">
                <div class="mr-modal__brand">
                    <img src="{{ asset($merchant->business_logo) }}"
                         alt="{{ $merchant->business_name }}"
                         class="mr-modal__logo"
                         loading="lazy">
                    <div class="mr-modal__brand-text">
                        <h5 class="mr-modal__title" id="MerchantDetailsLabel-{{ $merchant->id }}">{{ $merchant->business_name }}</h5>
                        <span class="mr-modal__subtitle">{{ $merchant->business_email }}</span>
                    </div>
                </div>

                <span class="mr-modal__status {{ $statusToneClass }}">
                    <span class="mr-modal__status-dot" aria-hidden="true"></span>
                    {{ $merchant->status->label() }}
                </span>

                <button type="button"
                        class="mr-modal__close"
                        data-coreui-dismiss="modal"
                        aria-label="{{ __('Close') }}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            {{-- Action form --}}
            <form action="{{ route('admin.merchant.request-action') }}" method="post">
                @csrf
                <input type="hidden" name="merchant_id" value="{{ $merchant->id }}">

                {{-- Body --}}
                <div class="modal-body mr-modal__body">

                    {{-- Status callout --}}
                    @if($merchant->status === \App\Enums\MerchantStatus::APPROVED)
                        <div class="mr-modal__callout mr-modal__callout--success" role="status">
                            <span class="mr-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-circle-check"></i>
                            </span>
                            <div>{{ __('This merchant is enabled and live.') }}</div>
                        </div>
                    @elseif($merchant->status === \App\Enums\MerchantStatus::DISABLED)
                        <div class="mr-modal__callout mr-modal__callout--warning" role="status">
                            <span class="mr-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </span>
                            <div>{{ __('This merchant is currently disabled.') }}</div>
                        </div>
                    @elseif($merchant->status === \App\Enums\MerchantStatus::PENDING)
                        <div class="mr-modal__callout mr-modal__callout--info" role="status">
                            <span class="mr-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </span>
                            <div>{{ __('This request is pending review. Approving will enable merchant operations.') }}</div>
                        </div>
                    @elseif($merchant->status === \App\Enums\MerchantStatus::REJECTED)
                        <div class="mr-modal__callout mr-modal__callout--danger" role="status">
                            <span class="mr-modal__callout-icon" aria-hidden="true">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </span>
                            <div>{{ __('This merchant request has been rejected.') }}</div>
                        </div>
                    @endif

                    {{-- Meta grid (replaces the chip wall) --}}
                    <div class="mr-modal__meta">
                        <div class="mr-modal__meta-item">
                            <span class="mr-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-fingerprint"></i>
                            </span>
                            <div class="mr-modal__meta-body">
                                <span class="mr-modal__meta-label">{{ __('Merchant ID') }}</span>
                                <span class="mr-modal__meta-value" title="{{ $merchant->merchant_key }}">{{ $merchant->merchant_key }}</span>
                            </div>
                        </div>

                        <div class="mr-modal__meta-item">
                            <span class="mr-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <div class="mr-modal__meta-body">
                                <span class="mr-modal__meta-label">{{ __('Owner') }}</span>
                                <span class="mr-modal__meta-value">
                                    <a href="{{ route('admin.user.manage', $merchant->user->username) }}"
                                       target="_blank"
                                       rel="noopener"
                                       title="{{ __('View user details') }}">{{ $merchant->user->name }}</a>
                                </span>
                            </div>
                        </div>

                        <div class="mr-modal__meta-item mr-modal__meta-item--full">
                            <span class="mr-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-link"></i>
                            </span>
                            <div class="mr-modal__meta-body">
                                <span class="mr-modal__meta-label">{{ __('Site URL') }}</span>
                                <span class="mr-modal__meta-value">
                                    <a href="{{ safeUrl($merchant->site_url) }}"
                                       target="_blank"
                                       rel="noopener"
                                       title="{{ $merchant->site_url }}">{{ $merchant->site_url }}</a>
                                </span>
                            </div>
                        </div>

                        <div class="mr-modal__meta-item">
                            <span class="mr-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-coins"></i>
                            </span>
                            <div class="mr-modal__meta-body">
                                <span class="mr-modal__meta-label">{{ __('Currencies') }}</span>
                                <span class="mr-modal__meta-value" title="{{ $currencies }}">{{ $currencies }}</span>
                            </div>
                        </div>

                        <div class="mr-modal__meta-item">
                            <span class="mr-modal__meta-icon" aria-hidden="true">
                                <i class="fa-solid fa-flask"></i>
                            </span>
                            <div class="mr-modal__meta-body">
                                <span class="mr-modal__meta-label">{{ __('Environment') }}</span>
                                <span class="mr-modal__meta-value">
                                    @if($merchant->isSandboxMode())
                                        <span class="mr-modal__meta-pill mr-modal__meta-pill--sandbox">{{ __('Sandbox') }}</span>
                                    @elseif($merchant->isProductionMode())
                                        <span class="mr-modal__meta-pill mr-modal__meta-pill--production">{{ __('Production') }}</span>
                                    @else
                                        {{ __('—') }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Business description --}}
                    <section class="mr-modal__section" aria-labelledby="mr-section-desc-{{ $merchant->id }}">
                        <header class="mr-modal__section-head">
                            <span class="mr-modal__section-icon" aria-hidden="true">
                                <i class="fa-solid fa-briefcase"></i>
                            </span>
                            <h3 class="mr-modal__section-title" id="mr-section-desc-{{ $merchant->id }}">{{ __('Requested Merchant') }}</h3>
                        </header>

                        <div class="mr-modal__quote">
                            <span class="mr-modal__quote-label">{{ __('Business Description') }}</span>
                            <div class="mr-modal__quote-text">
                                @if($description === '')
                                    <span class="mr-modal__quote-empty">{{ __('No description provided') }}</span>
                                @elseif(mb_strlen($description) > 180)
                                    <div id="{{ $descriptionId }}-short">
                                        {{ \Illuminate\Support\Str::limit($description, 180) }}
                                        <a class="mr-modal__quote-toggle"
                                           data-bs-toggle="collapse"
                                           href="#{{ $descriptionId }}-more"
                                           role="button"
                                           aria-expanded="false"
                                           aria-controls="{{ $descriptionId }}-more">{{ __('Show more') }}</a>
                                    </div>
                                    <div class="collapse" id="{{ $descriptionId }}-more">
                                        {!! nl2br(e($description)) !!}
                                        <a class="mr-modal__quote-toggle"
                                           data-bs-toggle="collapse"
                                           href="#{{ $descriptionId }}-more"
                                           role="button"
                                           aria-expanded="true"
                                           aria-controls="{{ $descriptionId }}-more">{{ __('Show less') }}</a>
                                    </div>
                                @else
                                    {!! nl2br(e($description)) !!}
                                @endif
                            </div>
                        </div>

                        <div class="mr-modal__request-meta">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            <span>{{ __('Request Date') }}: {{ $merchant->created_at?->format('Y-m-d H:i') }}</span>
                            <span class="mr-modal__request-meta-spacer"></span>
                            <span class="mr-modal__request-meta-ago">{{ $merchant->created_at?->diffForHumans() }}</span>
                        </div>
                    </section>

                    {{-- Fee configuration --}}
                    @if($merchant->status !== \App\Enums\MerchantStatus::REJECTED)
                        <section class="mr-modal__section" aria-labelledby="mr-section-fee-{{ $merchant->id }}">
                            <header class="mr-modal__section-head">
                                <span class="mr-modal__section-icon" aria-hidden="true">
                                    <i class="fa-solid fa-percent"></i>
                                </span>
                                <h3 class="mr-modal__section-title" id="mr-section-fee-{{ $merchant->id }}">{{ __('Merchant Transaction Fee') }}</h3>
                            </header>

                            <label class="mr-modal__fee-label" for="merchant_fee_{{ $merchant->id }}">
                                {{ __('Charge this percentage on every successful transaction') }}
                            </label>

                            <div class="mr-modal__fee-field">
                                <input type="text"
                                       id="merchant_fee_{{ $merchant->id }}"
                                       name="fee"
                                       class="mr-modal__fee-input @error('fee') is-invalid @enderror"
                                       value="{{ old('fee', $merchant->fee) }}"
                                       placeholder="0.00"
                                       inputmode="decimal"
                                       autocomplete="off"
                                       oninput="this.value = validateDouble(this.value)"
                                       aria-describedby="feeHint-{{ $merchant->id }}"
                                       title="{{ __('Percentage of each successful transaction charged to this merchant') }}">
                                <span class="mr-modal__fee-suffix" aria-hidden="true">%</span>
                            </div>

                            <p id="feeHint-{{ $merchant->id }}" class="mr-modal__fee-hint">
                                {{ __('This fee applies to successful transactions for this merchant.') }}
                            </p>

                            @error('fee')
                                <p class="mr-modal__fee-error">{{ $message }}</p>
                            @enderror
                        </section>
                    @endif

                    {{-- Operational note --}}
                    @if($merchant->status === \App\Enums\MerchantStatus::APPROVED)
                        <div class="mr-modal__note">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>{{ __('You can disable this merchant to pause payments and API access.') }}</span>
                        </div>
                    @elseif($merchant->status === \App\Enums\MerchantStatus::DISABLED)
                        <div class="mr-modal__note">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <span>{{ __('Enable the merchant to resume payments and API access.') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="modal-footer mr-modal__footer">
                    @if($merchant->status === \App\Enums\MerchantStatus::PENDING)
                        <button type="submit"
                                name="action"
                                value="approve"
                                class="mr-modal__btn mr-modal__btn--approve"
                                title="{{ __('Approve this merchant and enable operations') }}">
                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                            <span>{{ __('Approve') }}</span>
                        </button>
                        <button type="submit"
                                name="action"
                                value="reject"
                                class="mr-modal__btn mr-modal__btn--reject"
                                title="{{ __('Reject this merchant request') }}">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            <span>{{ __('Reject') }}</span>
                        </button>
                    @elseif($merchant->status === \App\Enums\MerchantStatus::APPROVED)
                        <button type="submit"
                                name="action"
                                value="approve"
                                class="mr-modal__btn mr-modal__btn--primary"
                                title="{{ __('Save the updated fee') }}">
                            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                            <span>{{ __('Update Fee') }}</span>
                        </button>
                        <button type="submit"
                                name="action"
                                value="disable"
                                class="mr-modal__btn mr-modal__btn--ghost-danger"
                                title="{{ __('Disable this merchant to pause payments and API access') }}">
                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                            <span>{{ __('Disable Merchant') }}</span>
                        </button>
                    @elseif($merchant->status === \App\Enums\MerchantStatus::DISABLED)
                        <button type="submit"
                                name="action"
                                value="enable"
                                class="mr-modal__btn mr-modal__btn--approve"
                                title="{{ __('Enable this merchant to resume payments and API access') }}">
                            <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                            <span>{{ __('Enable Merchant') }}</span>
                        </button>
                        <button type="submit"
                                name="action"
                                value="approve"
                                class="mr-modal__btn mr-modal__btn--ghost-primary"
                                title="{{ __('Save the updated fee') }}">
                            <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                            <span>{{ __('Update Fee') }}</span>
                        </button>
                    @else
                        <div class="mr-modal__footer-empty">
                            {{ __('This merchant is rejected. No further actions available.') }}
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
