@php
    $modalId = 'deposit-review-'.$transaction->id;
    $modalLabelId = $modalId.'-label';
    $remarksId = $modalId.'-remarks';
    $submittedDetails = is_array($transaction->trx_data) ? $transaction->trx_data : [];
    $statusTone = $transaction->status?->color() ?? 'warning';
@endphp

<div class="modal fade manual-request-review-modal manual-request-review-modal--deposit" id="review-{{ $transaction->id }}" tabindex="-1" aria-labelledby="{{ $modalLabelId }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down manual-request-review-modal__dialog">
        <div class="modal-content manual-request-review-modal__content">
            <div class="modal-header manual-request-review-modal__header">
                <div class="manual-request-review-modal__identity">
                    <span class="manual-request-review-modal__icon" aria-hidden="true">
                        <i class="fa-solid fa-wallet"></i>
                    </span>
                    <div class="manual-request-review-modal__title-group">
                        <span class="manual-request-review-modal__eyebrow">{{ __('Manual Deposit Review') }}</span>
                        <h5 class="modal-title manual-request-review-modal__title" id="{{ $modalLabelId }}">
                            {{ $transaction->user->name }}
                        </h5>
                        <span class="manual-request-review-modal__subtitle">{{ strtoupper($transaction->trx_id) }}</span>
                    </div>
                </div>

                <div class="manual-request-review-modal__header-actions">
                    <span class="manual-request-review-modal__status manual-request-review-modal__status--{{ $statusTone }}">
                        <span class="manual-request-review-modal__status-dot" aria-hidden="true"></span>
                        {{ $transaction->status?->label() ?? __('Pending') }}
                    </span>
                    <button type="button"
                            class="manual-request-review-modal__close"
                            data-coreui-dismiss="modal"
                            aria-label="{{ __('Close') }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <form action="{{ route('admin.deposit.request-action') }}" method="post">
                @csrf
                <input type="hidden" name="trx_id" value="{{ $transaction->trx_id }}">

                <div class="modal-body manual-request-review-modal__body">
                    <div class="manual-request-review-modal__callout" role="status">
                        <span class="manual-request-review-modal__callout-icon" aria-hidden="true">
                            <i class="fa-solid fa-info"></i>
                        </span>
                        <div>
                            <strong>{{ __('Verify the submitted payment proof before approving.') }}</strong>
                            <span>{{ __('Approving credits the user wallet; rejecting keeps the transaction from completing.') }}</span>
                        </div>
                    </div>

                    <section class="manual-request-review-modal__section" aria-labelledby="{{ $modalId }}-summary-title">
                        <div class="manual-request-review-modal__section-head">
                            <span class="manual-request-review-modal__section-icon" aria-hidden="true">
                                <i class="fa-solid fa-receipt"></i>
                            </span>
                            <div>
                                <h6 class="manual-request-review-modal__section-title" id="{{ $modalId }}-summary-title">{{ __('Deposit Summary') }}</h6>
                                <p class="manual-request-review-modal__section-subtitle">{{ __('Core request details from the transaction record.') }}</p>
                            </div>
                        </div>

                        <div class="manual-request-review-modal__summary-grid">
                            <div class="manual-request-review-modal__summary-item">
                                <span>{{ __('Transaction ID') }}</span>
                                <strong title="{{ $transaction->trx_id }}">{{ strtoupper($transaction->trx_id) }}</strong>
                            </div>
                            <div class="manual-request-review-modal__summary-item">
                                <span>{{ __('Payment Method') }}</span>
                                <strong>{{ \Illuminate\Support\Str::headline((string) $transaction->provider) }}</strong>
                            </div>
                            <div class="manual-request-review-modal__summary-item manual-request-review-modal__summary-item--highlight">
                                <span>{{ __('Payable Amount') }}</span>
                                <strong>{{ number_format((float) $transaction->net_amount, 2).' '.$transaction->payable_currency }}</strong>
                            </div>
                            <div class="manual-request-review-modal__summary-item">
                                <span>{{ __('Requested') }}</span>
                                <strong>{{ $transaction->created_at->format('Y-m-d H:i') }}</strong>
                            </div>
                        </div>
                    </section>

                    <section class="manual-request-review-modal__section" aria-labelledby="{{ $modalId }}-proof-title">
                        <div class="manual-request-review-modal__section-head">
                            <span class="manual-request-review-modal__section-icon" aria-hidden="true">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </span>
                            <div>
                                <h6 class="manual-request-review-modal__section-title" id="{{ $modalId }}-proof-title">{{ __('Submitted Payment Details') }}</h6>
                                <p class="manual-request-review-modal__section-subtitle">{{ __('User-provided account reference, notes, and uploaded files.') }}</p>
                            </div>
                        </div>

                        @if(!empty($submittedDetails))
                            <div class="manual-request-review-modal__proof-list">
                                @foreach($submittedDetails as $data)
                                    @php
                                        $fieldName = (string) ($data['name'] ?? __('Unknown Field'));
                                        $fieldLabel = \Illuminate\Support\Str::headline(str_replace('_', ' ', $fieldName));
                                        $fieldValue = $data['value'] ?? null;
                                        $isFileField = isset($data['type']) && $data['type'] === 'file';
                                    @endphp

                                    @if($isFileField)
                                        <div class="manual-request-review-modal__proof-file">
                                            <div class="manual-request-review-modal__proof-file-head">
                                                <span class="manual-request-review-modal__proof-file-icon" aria-hidden="true">
                                                    <i class="fa-solid fa-paperclip"></i>
                                                </span>
                                                <div>
                                                    <strong>{{ $fieldLabel }}</strong>
                                                    <span>{{ __('Uploaded payment proof') }}</span>
                                                </div>
                                            </div>

                                            @if(!empty($fieldValue))
                                                <a href="{{ asset($fieldValue) }}" target="_blank" rel="noopener" class="manual-request-review-modal__proof-preview">
                                                    <img src="{{ asset($fieldValue) }}" alt="{{ $fieldLabel }}" loading="lazy">
                                                    <span>{{ __('Open full proof') }}</span>
                                                </a>
                                            @else
                                                <div class="manual-request-review-modal__proof-empty">
                                                    <i class="fa-regular fa-file" aria-hidden="true"></i>
                                                    {{ __('No file uploaded') }}
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="manual-request-review-modal__proof-row">
                                            <span>{{ $fieldLabel }}</span>
                                            <strong>{{ filled($fieldValue) ? $fieldValue : __('N/A') }}</strong>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <x-admin-not-found
                                :title="__('No submitted details')"
                                :message="__('The user did not attach any manual deposit details to this request.')"
                                icon="fa-receipt"
                                class="manual-request-review-modal__empty"
                            />
                        @endif
                    </section>

                    <section class="manual-request-review-modal__section" aria-labelledby="{{ $modalId }}-remarks-title">
                        <div class="manual-request-review-modal__section-head manual-request-review-modal__section-head--compact">
                            <span class="manual-request-review-modal__section-icon" aria-hidden="true">
                                <i class="fa-solid fa-message"></i>
                            </span>
                            <div>
                                <label class="manual-request-review-modal__section-title" id="{{ $modalId }}-remarks-title" for="{{ $remarksId }}">
                                    {{ __('Review Remarks') }}
                                </label>
                                <p class="manual-request-review-modal__section-subtitle">{{ __('Optional note saved with the approval decision.') }}</p>
                            </div>
                        </div>

                        <textarea class="form-control manual-request-review-modal__remarks"
                                  name="remarks"
                                  id="{{ $remarksId }}"
                                  rows="3"
                                  placeholder="{{ __('Add context for this decision...') }}"></textarea>
                    </section>
                </div>

                <div class="modal-footer manual-request-review-modal__footer">
                    <button type="submit" value="reject" name="action" class="btn manual-request-review-modal__action manual-request-review-modal__action--reject">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        {{ __('Reject Deposit') }}
                    </button>
                    <button type="submit" value="approve" name="action" class="btn manual-request-review-modal__action manual-request-review-modal__action--approve">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        {{ __('Approve Deposit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
