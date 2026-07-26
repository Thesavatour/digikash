@php use App\Enums\TrxType; @endphp
<div class="modal fade urk-modal" id="new_user_rank_modal" tabindex="-1" aria-labelledby="newUserRankModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            {{-- Modal Header --}}
            <div class="modal-header urk-modal__header">
                <span class="urk-modal__header-icon"><i class="fa-solid fa-award"></i></span>
                <div class="urk-modal__header-text">
                    <span class="urk-modal__eyebrow">{{ __('Progression Tier') }}</span>
                    <h5 class="urk-modal__title" id="newUserRankModalLabel">{{ __('Add New User Rank') }}</h5>
                </div>
                <button type="button" class="urk-modal__close" data-coreui-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            {{-- Modal Body --}}
            <div class="modal-body">
                <form action="{{ route('admin.ranking.store') }}" method="POST" enctype="multipart/form-data"
                      class="urk-form">
                    @csrf

                    {{-- Identity --}}
                    <div class="urk-section">
                        <p class="urk-section__title"><i class="fa-solid fa-id-badge"></i>{{ __('Rank Identity') }}</p>

                        <div class="urk-field">
                            <label class="urk-label" for="icon">{{ __('Icon') }}</label>
                            <div class="urk-iconpicker">
                                <div class="urk-iconpicker__preview">
                                    <x-img name="icon"/>
                                </div>
                                <div class="urk-iconpicker__hint">
                                    <strong>{{ __('Upload a rank badge') }}</strong>
                                    <span>{{ __('PNG, JPG or SVG. A square image works best.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="urk-field">
                            <label for="rank-name" class="urk-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control" id="rank-name" name="name" required
                                   placeholder="{{ __('Enter rank name') }}">
                        </div>

                        <div class="urk-field">
                            <label for="rank-description" class="urk-label">{{ __('Description') }}</label>
                            <input class="form-control" id="rank-description" name="description" required
                                   placeholder="{{ __('Enter rank description') }}">
                        </div>
                    </div>

                    {{-- Economics --}}
                    <div class="urk-section">
                        <p class="urk-section__title"><i class="fa-solid fa-coins"></i>{{ __('Economics') }}</p>
                        <div class="urk-grid">
                            <div class="urk-field">
                                <label for="trx-amount" class="urk-label">{{ __('Transaction Amount') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="transaction_amount"
                                           oninput="this.value = validateDouble(this.value)"
                                           aria-label="Amount (to the nearest dollar)">
                                    <span class="input-group-text">{{ siteCurrency() }}</span>
                                </div>
                            </div>
                            <div class="urk-field">
                                <label for="trx-amount" class="urk-label">{{ __('Rank Reward') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="reward"
                                           oninput="this.value = validateDouble(this.value)"
                                           aria-label="Amount (to the nearest dollar)">
                                    <span class="input-group-text">{{ siteCurrency() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Permissions --}}
                    <div class="urk-section">
                        <p class="urk-section__title">
                            <i class="fa-solid fa-shield-halved"></i>{{ __('Allow Transaction Type') }}
                        </p>
                        @php($rankTrxTypes = TrxType::userRankSupport())
                        <div class="urk-trx-grid">
                            @forelse($rankTrxTypes as $trxType)
                                <label class="urk-trx-option" for="trx-type-create-{{ $trxType->value }}">
                                    <input class="form-check-input" type="checkbox" name="transaction_types[]"
                                           id="trx-type-create-{{ $trxType->value }}"
                                           value="{{ $trxType->value }}">
                                    <span class="urk-trx-option__label">{{ $trxType->label() }}</span>
                                </label>
                            @empty
                                <div class="urk-trx-empty">
                                    {{ __('No transaction features are currently active. Enable them from Feature Management.') }}
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Limits --}}
                    <div class="urk-section">
                        <p class="urk-section__title"><i class="fa-solid fa-sliders"></i>{{ __('Limits') }}</p>
                        <div class="urk-grid">
                            <div class="urk-field">
                                <label for="trx-amount" class="urk-label">{{ __('Max Wallet Create') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="features[wallet_create]"
                                           oninput="this.value = validateDouble(this.value)"
                                           aria-label="Wallet Create">
                                    <span class="input-group-text">{{ __('Wallet') }}</span>
                                </div>
                            </div>
                            <div class="urk-field">
                                <label for="trx-amount" class="urk-label">{{ __('Max Referral Level') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="features[referral_level]"
                                           oninput="this.value = validateDouble(this.value)"
                                           aria-label="Referral Level">
                                    <span class="input-group-text">{{ __('Level') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="urk-field mt-3">
                            <div class="urk-switch">
                                <div class="urk-switch__text">
                                    <strong>{{ __('Status') }}</strong>
                                    <span>{{ __('Make this rank active and selectable.') }}</span>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input coevs-switch" type="checkbox" id="is-active"
                                           name="is_active" value="1" checked>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                                data-coreui-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Save Rank') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
