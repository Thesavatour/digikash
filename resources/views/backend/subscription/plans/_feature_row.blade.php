@php
    $planFeatures = [
        'daily_transaction_limit'   => 'Daily Transactions',
        'monthly_transaction_limit' => 'Monthly Transactions',
        'monthly_withdraw_limit'    => 'Monthly Withdrawal',
        'monthly_send_limit'        => 'Monthly Send',
        'wallet_balance_cap'        => 'Wallet Balance',
        'p2p_marketplace_browse'    => 'P2P Marketplace Browsing',
        'p2p_trading'               => 'P2P Trading',
        'p2p_ad_limit'              => 'P2P Ads',
        'p2p_trade_limit'           => 'P2P Trades',
        'virtual_cards'             => 'Virtual Cards',
        'virtual_card_limit'        => 'Virtual Card Slots',
        'wallet_earn'               => 'Wallet Earn / Staking',
        'withdrawal_speed'          => 'Withdrawal Speed',
        'support_priority'          => 'Support',
        'api_access'                => 'API Access',
    ];

    $featureTypes = [
        'limit'   => 'Limit',
        'quota'   => 'Quota',
        'access'  => 'Access',
        'benefit' => 'Benefit',
    ];

    $currentKey   = old('features.'.$i.'.feature_key',   $feature['feature_key']   ?? '');
    $currentLabel = old('features.'.$i.'.feature_label', $feature['feature_label'] ?? '');
    $currentType  = old('features.'.$i.'.feature_type',  $feature['feature_type']  ?? 'limit');
    $currentValue = old('features.'.$i.'.feature_value', $feature['feature_value'] ?? '');

    if ($currentType === 'toggle') {
        $currentType = 'access';
    }

    if (! array_key_exists($currentType, $featureTypes)) {
        $currentType = 'limit';
    }

    if ($currentType === 'access' && $currentValue === '') {
        $currentValue = 'enabled';
    }
@endphp

<div class="feature-row" data-feature-type="{{ $currentType }}">
    <input type="hidden" name="features[{{ $i }}][sort_order]" class="feature-sort-order" value="{{ $i }}">

    <div class="feature-row__card">
        <div class="feature-row__drag">
            <div class="feature-drag-handle" title="@lang('Drag to reorder')">
                <i class="fas fa-grip-vertical"></i>
            </div>
        </div>

        <div class="feature-row__field">
            <label class="form-label small mb-1 text-muted fw-semibold">@lang('Feature')</label>
            <select name="features[{{ $i }}][feature_key]"
                    class="form-select form-select-sm feature-key-select"
                    data-label-target="feature-label-{{ $i }}">
                <option value="">- @lang('Select') -</option>
                @foreach($planFeatures as $fk => $fl)
                    <option value="{{ $fk }}"
                            data-label="{{ __($fl) }}"
                            @selected($currentKey === $fk)>
                        {{ __($fl) }}
                    </option>
                @endforeach
                @if($currentKey && ! array_key_exists($currentKey, $planFeatures))
                    <option value="{{ $currentKey }}" data-label="{{ $currentLabel }}" selected>{{ $currentKey }}</option>
                @endif
            </select>
        </div>

        <div class="feature-row__field">
            <label class="form-label small mb-1 text-muted fw-semibold">@lang('Label')</label>
            <input type="text" name="features[{{ $i }}][feature_label]"
                   id="feature-label-{{ $i }}"
                   class="form-control form-control-sm"
                   placeholder="{{ __('e.g. Daily Transactions') }}"
                   value="{{ $currentLabel }}">
        </div>

        <div class="feature-row__field feature-row__field--type">
            <label class="form-label small mb-1 text-muted fw-semibold">@lang('Type')</label>
            <select name="features[{{ $i }}][feature_type]" class="form-select form-select-sm feature-type-select">
                @foreach($featureTypes as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}" @selected($currentType === $typeValue)>@lang($typeLabel)</option>
                @endforeach
            </select>
        </div>

        <div class="feature-row__field feature-row__field--value">
            <label class="form-label small mb-1 text-muted fw-semibold">@lang('Value')</label>
            <input type="text"
                   name="features[{{ $i }}][feature_value]"
                   class="form-control form-control-sm feature-val-text {{ $currentType === 'access' ? 'd-none' : '' }}"
                   placeholder="{{ __('e.g. 50 / unlimited / Priority') }}"
                   value="{{ $currentValue }}">
            <select class="form-select form-select-sm feature-access-select {{ $currentType === 'access' ? '' : 'd-none' }}">
                <option value="enabled" @selected(strtolower((string) $currentValue) !== 'disabled')>@lang('Enabled')</option>
                <option value="disabled" @selected(strtolower((string) $currentValue) === 'disabled')>@lang('Disabled')</option>
            </select>
        </div>

        <div class="feature-row__action">
            <button type="button" class="btn btn-sm btn-outline-danger remove-feature-btn feature-remove-btn" title="@lang('Remove')">
                <x-icon name="trash" height="13" width="13" class="feature-remove-btn__icon"/>
            </button>
        </div>
    </div>
</div>
