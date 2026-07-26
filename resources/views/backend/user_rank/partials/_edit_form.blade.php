@php use App\Enums\TrxType; @endphp
<form action="{{ route('admin.ranking.update', $userRank->id) }}" method="POST" enctype="multipart/form-data"
      class="urk-form">
    @csrf
    @method('PUT')

    {{-- Identity --}}
    <div class="urk-section">
        <p class="urk-section__title"><i class="fa-solid fa-id-badge"></i>{{ __('Rank Identity') }}</p>

        <div class="urk-field">
            <label class="urk-label" for="icon">{{ __('Icon') }}</label>
            <div class="urk-iconpicker">
                <div class="urk-iconpicker__preview">
                    <x-img name="icon" :value="$userRank->icon" :old="$userRank->icon" :ref="'coevs-user-rank-icon'"/>
                </div>
                <div class="urk-iconpicker__hint">
                    <strong>{{ __('Upload a rank badge') }}</strong>
                    <span>{{ __('PNG, JPG or SVG. A square image works best.') }}</span>
                </div>
            </div>
        </div>

        <div class="urk-field">
            <label for="rank-name" class="urk-label">{{ __('Name') }}</label>
            <input type="text" class="form-control" id="rank-name" name="name" value="{{ $userRank->name }}" required
                   placeholder="{{ __('Enter rank name') }}">
        </div>

        <div class="urk-field">
            <label for="rank-description" class="urk-label">{{ __('Description') }}</label>
            <input class="form-control" id="rank-description" name="description" value="{{ $userRank->description }}"
                   required placeholder="{{ __('Enter rank description') }}">
        </div>
    </div>

    {{-- Economics --}}
    <div class="urk-section">
        <p class="urk-section__title"><i class="fa-solid fa-coins"></i>{{ __('Economics') }}</p>
        <div class="urk-grid">
            <div class="urk-field">
                <label for="trx-amount" class="urk-label">
                    {{ __('Transaction Amount') }}
                    @if($userRank->is_default)
                        <span class="modal-tooltip" data-coreui-toggle="tooltip" data-coreui-placement="top"
                              title="{{ __('Default Rank Not Allowed To Change Transaction Amount') }}">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    @endif
                </label>
                <div class="input-group">
                    <input type="text" class="form-control"
                           name="transaction_amount" oninput="this.value = validateDouble(this.value)"
                           @if($userRank->is_default) disabled @endif
                           value="{{ $userRank->transaction_amount }}"
                           aria-label="Amount (to the nearest dollar)">
                    <span class="input-group-text">{{ siteCurrency() }}</span>
                </div>
            </div>
            <div class="urk-field">
                <label for="trx-amount" class="urk-label">{{ __('Rank Reward') }}</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="reward"
                           oninput="this.value = validateDouble(this.value)" value="{{ $userRank->reward }}"
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
                <label class="urk-trx-option" for="trx-type-{{ $trxType->value }}">
                    <input class="form-check-input" type="checkbox" name="transaction_types[]"
                           id="trx-type-{{ $trxType->value }}"
                           value="{{ $trxType->value }}"
                           @checked(in_array($trxType->value, (array) $userRank->transaction_types, true))>
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
                           value="{{ data_get($userRank->features, 'wallet_create', 0) }}"
                           aria-label="Wallet Create">
                    <span class="input-group-text">{{ __('Wallet') }}</span>
                </div>
            </div>
            <div class="urk-field">
                <label for="trx-amount" class="urk-label">{{ __('Max Referral Level') }}</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="features[referral_level]"
                           value="{{ data_get($userRank->features, 'referral_level', 0) }}"
                           oninput="this.value = validateDouble(this.value)"
                           aria-label="Referral Level">
                    <span class="input-group-text">{{ __('Level') }}</span>
                </div>
            </div>
        </div>

        <div class="urk-field mt-3">
            <div class="urk-switch">
                <div class="urk-switch__text">
                    <strong>
                        {{ __('Status') }}
                        @if($userRank->is_default)
                            <span class="modal-tooltip" data-coreui-toggle="tooltip" data-coreui-placement="top"
                                  title="{{ __('Default Rank Status Cannot Be Changed - Always Active') }}">
                                <i class="fa-solid fa-circle-info"></i>
                            </span>
                        @endif
                    </strong>
                    <span>{{ __('Make this rank active and selectable.') }}</span>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input coevs-switch" type="checkbox" id="is-active" name="is_active"
                           value="1" @checked($userRank->is_active) @if($userRank->is_default) disabled @endif>
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
