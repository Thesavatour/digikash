<div class="modal fade p2p-ui-modal p2p-account-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header p2p-account-modal__header">
                <div class="p2p-account-modal__heading">
                    <span class="p2p-account-modal__icon" aria-hidden="true"><i class="fas fa-building-columns"></i></span>
                    <div class="p2p-account-modal__heading-text">
                        <h5 class="modal-title p2p-account-modal__title">{{ $title }}</h5>
                        <p class="p2p-account-modal__subtitle">@lang('Save a payment method you own so it is ready for your P2P trades.')</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('Close')"></button>
            </div>
            <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" enctype="multipart/form-data">
                @csrf
                @if($httpMethod)
                    @method($httpMethod)
                @endif
                <input type="hidden" name="form_type" value="{{ $formType }}">
                @if($accountIdInputId)
                    <input type="hidden" name="account_id" id="{{ $accountIdInputId }}" value="{{ $accountIdValue }}">
                @endif
                <div class="modal-body p2p-account-modal__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $methodSelectId }}">@lang('Payment Method')</label>
                            <select name="payment_method_id" id="{{ $methodSelectId }}" class="form-select" required>
                                <option value="">@lang('Select Payment Method')</option>
                                @foreach($methodOptions as $methodOption)
                                    <option value="{{ $methodOption['id'] }}" @selected((string) $methodOption['id'] === (string) ($selectedPaymentMethodId ?? ''))>
                                        {{ $methodOption['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $labelInputId }}">@lang('Account Label')</label>
                            <input
                                type="text"
                                name="label"
                                id="{{ $labelInputId }}"
                                class="form-control"
                                value="{{ $labelValue ?? '' }}"
                                placeholder="@lang('Example: Personal account')"
                            >
                        </div>
                        <div class="col-12">
                            <div id="{{ $infoId }}" class="p2p-account-modal__info d-none" role="note"></div>
                        </div>
                        <div class="col-12">
                            <div id="{{ $fieldsWrapId }}" class="row g-3"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p2p-account-modal__footer">
                    <span class="p2p-account-modal__footer-note">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                        @lang('Saved privately — shared with a counterparty only during an active trade.')
                    </span>
                    <div class="p2p-account-modal__footer-actions">
                        <button type="button" class="btn btn-light p2p-account-modal__cancel" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn-base p2p-account-modal__save">
                            <i class="fas fa-save me-1"></i> {{ $submitLabel }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
