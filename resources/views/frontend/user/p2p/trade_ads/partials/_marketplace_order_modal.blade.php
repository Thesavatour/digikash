{{-- Order Create Modal --}}
<div class="modal fade p2p-ui-modal p2p-ui" id="p2pOrderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content p2p-order-modal">
      <div class="p2p-order-modal__head">
        <div class="p2p-order-modal__title-row">
          <span id="p2pModalSideBadge" class="p2p-order-side-badge">@lang('Trade')</span>
          <span id="p2pModalCoinBadge" class="p2p-order-coin-badge d-none" aria-hidden="true"></span>
          <h5 class="p2p-order-modal__title" id="p2pModalTitle">@lang('Start Trade')</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="p2p-order-modal__subhead">
        <div class="p2p-order-seller">
          <div class="p2p-order-seller__avatar" id="p2pModalAvatarWrap">
            <img id="p2pModalAvatarImg" class="d-none" src="" alt="" loading="lazy">
            <span id="p2pModalAvatarText">-</span>
          </div>
          <div class="p2p-order-seller__meta min-w-0">
            <div class="p2p-order-seller__name-row">
              <a href="#" id="p2pModalAdvertiserLink" class="p2p-order-seller__name">-</a>
              <span id="p2pModalVerified" class="p2p-seller-badge p2p-seller-badge--verified d-none">
                <i class="fas fa-check-circle"></i>@lang('Verified')
              </span>
            </div>
            <div class="p2p-order-seller__stats">
              <span class="p2p-order-seller__rating">
                <i class="fas fa-star text-warning"></i>
                <span id="p2pModalRating">-</span>
              </span>
              <span class="p2p-dot">•</span>
              <span id="p2pModalCompletion">-</span>% @lang('completion')
              <span class="p2p-dot">•</span>
              <span id="p2pModalTrades">-</span> @lang('trades')
            </div>
          </div>
        </div>
        <div class="p2p-order-ratebox">
          <div class="p2p-order-ratebox__label">@lang('Rate')</div>
          <div class="p2p-order-ratebox__value" id="p2pModalRate">-</div>
          <span id="p2pModalRateTrend" class="p2p-order-rate-trend d-none"></span>
        </div>
      </div>

      <form method="POST" action="{{ route('user.p2p.orders.store') }}" class="p2p-order-modal__form">
        @csrf
        <div class="modal-body p2p-order-modal__body">
          <input type="hidden" name="offer_id" id="p2p_offer_id">
          <input type="hidden" name="payment_account_id" id="p2p_payment_account_id">

          <div class="p2p-order-grid">
            <div class="p2p-order-main">
              <div class="p2p-order-card">
                <div class="p2p-order-card__head">
                  <div class="p2p-order-card__title">
                    <span class="p2p-order-step">1</span>
                    <span id="p2pModalAmountTitle">@lang('Amount')</span>
                  </div>
                  <div class="p2p-order-card__meta text-muted">
                    @lang('Available'):
                    <strong class="text-dark"><span id="p2pModalAvailable">-</span> <span id="p2pModalCurrency">-</span></strong>
                  </div>
                </div>

                <div class="p2p-order-convert" id="p2pModalConvertRow">
                  <div class="p2p-order-input">
                    <input type="text"
                           inputmode="decimal"
                           class="p2p-order-input__control"
                           name="amount"
                           id="p2p_amount"
                           required
                           placeholder="0.00"
                           oninput="this.value = validateDouble(this.value)">
                    <span class="p2p-order-input__unit" id="p2pModalCurrencyUnit">-</span>
                  </div>
                  <button type="button" class="p2p-order-swapbtn" id="p2pModalSwapBtn" title="@lang('Swap direction')" aria-label="@lang('Swap direction')">
                    <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                  </button>
                  <div class="p2p-order-input">
                    <input type="text"
                           inputmode="decimal"
                           class="p2p-order-input__control"
                           id="p2p_fiat_amount"
                           placeholder="-"
                           oninput="this.value = validateDouble(this.value)">
                    <span class="p2p-order-input__unit" id="p2pModalFiatCcy">-</span>
                  </div>
                </div>

                <div class="p2p-order-quickchips" id="p2pModalQuickChips" role="group" aria-label="@lang('Quick amount')">
                  <button type="button" class="p2p-order-quickchip" data-quick="25">25%</button>
                  <button type="button" class="p2p-order-quickchip" data-quick="50">50%</button>
                  <button type="button" class="p2p-order-quickchip" data-quick="75">75%</button>
                  <button type="button" class="p2p-order-quickchip" data-quick="100">@lang('MAX')</button>
                </div>

                <div class="p2p-order-note">
                  <span class="text-muted">@lang('Limit'):</span>
                  <strong><span id="p2pModalLimit">-</span> <span id="p2pModalLimitCcy">-</span></strong>
                  <span id="p2pModalAmountHint" class="p2p-order-amount-hint"></span>
                </div>
              </div>

              <div class="p2p-order-card">
                <div class="p2p-order-card__head">
                  <div class="p2p-order-card__title">
                    <span class="p2p-order-step">2</span>
                    <span id="p2pModalPaymentCardTitle">@lang('Payment Account')</span>
                  </div>
                  <span class="p2p-order-chip" id="p2pModalPaymentCardBadge">@lang('Required')</span>
                </div>

                <div id="p2pModalPaymentFlowText" class="small text-muted mt-1"></div>

                <div id="p2pModalAcceptedMethodsWrap" class="mt-2 d-none">
                  <div class="small text-muted mb-2">@lang('Advertiser payment methods')</div>
                  <div id="p2pModalAcceptedMethods" class="p2p-payment-pills"></div>
                </div>

                <div class="p2p-order-payment-selectwrap">
                  <select id="p2pModalPaymentSelect" class="form-select p2p-order-payment-select" data-no-premium-select>
                    <option value="">@lang('Select Payment Account')</option>
                  </select>
                </div>

                <div id="p2pModalPaymentInstructions" class="p2p-order-instructions d-none"></div>
              </div>

              <div class="p2p-order-card p2p-order-summary-card">
                <div class="p2p-order-card__head">
                  <div class="p2p-order-card__title">
                    <span class="p2p-order-step">3</span>
                    <span>@lang('Order Summary')</span>
                  </div>
                  <i class="fas fa-lock text-muted"></i>
                </div>

                <div class="p2p-order-summary-box">
                <div class="p2p-order-summary-rows">
                  <div class="p2p-order-summary-row">
                    <div class="p2p-order-summary-row__left">
                      <i class="fas fa-arrow-down text-success"></i>
                      <span class="text-muted">@lang('You will receive')</span>
                    </div>
                    <strong class="text-success"><span id="p2pModalReceive">-</span> <span id="p2pModalReceiveCcy">-</span></strong>
                  </div>
                  <div class="p2p-order-summary-row">
                    <div class="p2p-order-summary-row__left">
                      <i class="fas fa-exchange-alt text-muted"></i>
                      <span class="text-muted">@lang('Exchange Rate')</span>
                    </div>
                    <strong><span id="p2pModalExchangeRate">-</span></strong>
                  </div>
                  <div class="p2p-order-summary-row">
                    <div class="p2p-order-summary-row__left">
                      <i class="fas fa-receipt text-muted"></i>
                      <span class="text-muted">@lang('Trading Fee')</span>
                      <span id="p2pModalFeeFree" class="p2p-order-fee-free d-none">@lang('FREE')</span>
                    </div>
                    <strong><span id="p2pModalFeeValue">0.00</span> <span id="p2pModalFeeValueCcy">-</span> (<span id="p2pModalFee">0</span>%)</strong>
                  </div>
                  <div class="p2p-order-summary-row">
                    <div class="p2p-order-summary-row__left">
                      <i class="fas fa-arrow-up text-dark"></i>
                      <span class="text-muted">@lang('You will pay')</span>
                    </div>
                    <strong><span id="p2pModalPay">-</span> <span id="p2pModalPayCcy">-</span></strong>
                  </div>
                </div>
                </div>
              </div>

              <div class="p2p-order-card p2p-order-terms">
                <div class="p2p-order-card__title">
                  <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                  @lang('Terms & Conditions')
                </div>

                <label class="p2p-order-terms-agree">
                  <input class="form-check-input" type="checkbox" name="agree_terms" id="p2pTermsAgree" required>
                  <span>@lang('I have read and agree to the terms')</span>
                </label>

                <ul id="p2pModalTermsList" class="p2p-order-terms__list"></ul>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer p2p-order-modal__footer">
          <div class="p2p-order-footer-release">
            <span class="p2p-order-footer-release__icon" aria-hidden="true">
              <i class="far fa-clock"></i>
            </span>
            <div class="p2p-order-footer-release__meta">
              <div class="p2p-order-footer-release__label">@lang('Est. Release Time')</div>
              <div class="p2p-order-footer-release__value" id="p2pModalReleaseTime">-</div>
            </div>
          </div>

          <div class="p2p-order-footer-actions">
            <button type="button" class="btn btn-outline-secondary p2p-order-cancel" data-bs-dismiss="modal">
              @lang('Cancel')
            </button>
            <button type="submit" id="p2pModalSubmitBtn" class="btn p2p-order-submit" disabled>
              <i class="fas fa-lock me-2"></i>
              <span id="p2pModalSubmitText">@lang('Confirm')</span>
              <i class="fas fa-arrow-right ms-2"></i>
            </button>
          </div>

          <div class="p2p-order-footer-note">
            <i class="fas fa-shield-halved" aria-hidden="true"></i>
            @lang('Funds held in escrow until payment is confirmed')
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
