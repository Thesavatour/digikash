<script>
"use strict";
document.addEventListener('DOMContentLoaded', function () {
    @php
        $userPaymentAccountsPayload = collect($userPaymentAccounts ?? [])->map(function ($account) {
            return $account->toTradeSnapshot();
        })->values()->all();
    @endphp

    const lang = {
        noSavedPaymentAccounts: @json(__('No saved payment accounts available')),
        selectPaymentAccount: @json(__('Select Payment Account')),
        defaultTermSavedAccount: @json(__('Use only the saved payment account selected for this trade.')),
        defaultTermNoNotes: @json(__('Do not write any notes or purpose with the payment.')),
        defaultTermConfirmAfterReceipt: @json(__('Confirm the payment only after receiving the full amount.')),
        defaultTermLocalLaw: @json(__('Abide by all applicable local laws and regulations.')),
        method: @json(__('Method')),
        account: @json(__('Account')),
        paymentInstructions: @json(__('Payment Instructions')),
        noMatchingAccount: @json(__('You do not have any saved payment account matching the advertiser payment methods. Add one first to continue.')),
        needSavedAccount: @json(__('You need at least one saved payment account before starting this trade.')),
        amountToSell: @json(__('Amount to Sell')),
        amountToBuy: @json(__('Amount to Buy')),
        securely: @json(__('Securely')),
        sell: @json(__('Sell')),
        buy: @json(__('Buy')),
        min: @json(__('min')),
        yourReceivingAccount: @json(__('Your Receiving Account')),
        yourPaymentAccount: @json(__('Your Payment Account')),
        receivingAccount: @json(__('Receiving Account')),
        buyerAccount: @json(__('Buyer Account')),
        receivingAccountHint: @json(__('Choose the saved account where you want to receive this trade payment.')),
        buyerAccountHint: @json(__('Choose one of your saved payment accounts that matches the advertiser payment methods.')),
        confirm: @json(__('Confirm')),
        pending: @json(__('Pending')),
        paid: @json(__('Paid')),
        disputed: @json(__('Disputed')),
        updated: @json(__('Updated')),
        order: @json(__('Order')),
        release: @json(__('Release')),
        minimumIs: @json(__('Minimum is')),
        maximumIs: @json(__('Maximum is')),
        withinLimit: @json(__('Within limit')),
        copied: @json(__('Copied')),
        free: @json(__('FREE')),
        vsMarket: @json(__('vs market')),
        enterAmount: @json(__('Enter amount')),
        agreeTerms: @json(__('Agree to the terms')),
    };

    const currentUserId = Number(@json((int) auth()->id()));
    const addPaymentAccountUrlTemplate = @json(route('user.p2p.payment-accounts.index', ['create' => 1, 'payment_method_id' => '__METHOD_ID__']));
    const addAccountChipTitle = @json(__('You have not saved an account for this method yet — click to add one.'));
    const readyAccountChipTitle = @json(__('You have a saved account for this method.'));

    const initPopovers = function () {
        if (!window.bootstrap || typeof window.bootstrap.Popover !== 'function') {
            return;
        }
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            const inst = window.bootstrap.Popover.getInstance(el);
            if (inst) {
                inst.dispose();
            }
            new window.bootstrap.Popover(el);
        });
    };

    const modalEl = document.getElementById('p2pOrderModal');
    if (!modalEl) return;

    const dom = {
        offersList: document.getElementById('p2pOffersList'),
        offersItems: document.getElementById('p2pOffersItems'),
        offersLoader: document.getElementById('p2pOffersLoader'),
        offersEnd: document.getElementById('p2pOffersEnd'),
        offersPagination: document.getElementById('p2pOffersPagination'),
        offerId: document.getElementById('p2p_offer_id'),
        amount: document.getElementById('p2p_amount'),
        paymentAccountId: document.getElementById('p2p_payment_account_id'),
        sideBadge: document.getElementById('p2pModalSideBadge'),
        title: document.getElementById('p2pModalTitle'),
        amountTitle: document.getElementById('p2pModalAmountTitle'),
        rate: document.getElementById('p2pModalRate'),
        exchangeRate: document.getElementById('p2pModalExchangeRate'),
        currency: document.getElementById('p2pModalCurrency'),
        currencyUnit: document.getElementById('p2pModalCurrencyUnit'),
        available: document.getElementById('p2pModalAvailable'),
        limit: document.getElementById('p2pModalLimit'),
        limitCcy: document.getElementById('p2pModalLimitCcy'),
        fiatInput: document.getElementById('p2p_fiat_amount'),
        fiatCcy: document.getElementById('p2pModalFiatCcy'),
        convertRow: document.getElementById('p2pModalConvertRow'),
        swapBtn: document.getElementById('p2pModalSwapBtn'),
        pay: document.getElementById('p2pModalPay'),
        payCcy: document.getElementById('p2pModalPayCcy'),
        receive: document.getElementById('p2pModalReceive'),
        receiveCcy: document.getElementById('p2pModalReceiveCcy'),
        fee: document.getElementById('p2pModalFee'),
        feeValue: document.getElementById('p2pModalFeeValue'),
        feeValueCcy: document.getElementById('p2pModalFeeValueCcy'),
        paymentSelect: document.getElementById('p2pModalPaymentSelect'),
        paymentInstructions: document.getElementById('p2pModalPaymentInstructions'),
        paymentCardTitle: document.getElementById('p2pModalPaymentCardTitle'),
        paymentCardBadge: document.getElementById('p2pModalPaymentCardBadge'),
        paymentFlowText: document.getElementById('p2pModalPaymentFlowText'),
        acceptedMethodsWrap: document.getElementById('p2pModalAcceptedMethodsWrap'),
        acceptedMethods: document.getElementById('p2pModalAcceptedMethods'),
        termsList: document.getElementById('p2pModalTermsList'),
        termsAgree: document.getElementById('p2pTermsAgree'),
        releaseTime: document.getElementById('p2pModalReleaseTime'),
        submitBtn: document.getElementById('p2pModalSubmitBtn'),
        submitText: document.getElementById('p2pModalSubmitText'),
        advertiserLink: document.getElementById('p2pModalAdvertiserLink'),
        avatarWrap: document.getElementById('p2pModalAvatarWrap'),
        avatarImg: document.getElementById('p2pModalAvatarImg'),
        avatarText: document.getElementById('p2pModalAvatarText'),
        verified: document.getElementById('p2pModalVerified'),
        rating: document.getElementById('p2pModalRating'),
        completion: document.getElementById('p2pModalCompletion'),
        trades: document.getElementById('p2pModalTrades'),
        modalContent: modalEl.querySelector('.p2p-order-modal'),
        coinBadge: document.getElementById('p2pModalCoinBadge'),
        rateTrend: document.getElementById('p2pModalRateTrend'),
        quickChips: document.getElementById('p2pModalQuickChips'),
        amountHint: document.getElementById('p2pModalAmountHint'),
        feeFree: document.getElementById('p2pModalFeeFree'),
    };

    const userPaymentAccounts = @json($userPaymentAccountsPayload);

    const state = {
        price: 0,
        currency: '-',
        fiatCurrency: '-',
        action: 'buy',
        actionLabel: '',
        minAmount: 0,
        maxAmount: null,
        terms: null,
        acceptedPaymentMethods: [],
        eligiblePaymentAccounts: [],
        selectedPaymentAccountId: null,
        emptyPaymentAccountMessage: '',
        offersLastPage: Math.max(Number(dom.offersList ? dom.offersList.dataset.lastPage : 1) || 1, 1),
        offersNextPage: Math.max(Number(dom.offersList ? dom.offersList.dataset.nextPage : 0) || 0, 0),
        offersLoading: false,
        offersAppended: false,
    };

    let offersEndTimer = null;

    const toggleOffersLoader = function (show) {
        if (!dom.offersLoader) {
            return;
        }

        dom.offersLoader.classList.toggle('d-none', !show);
    };

    const toggleOffersEnd = function (show) {
        if (!dom.offersEnd) {
            return;
        }

        if (offersEndTimer) {
            clearTimeout(offersEndTimer);
            offersEndTimer = null;
        }

        dom.offersEnd.classList.toggle('d-none', !show);

        if (show) {
            offersEndTimer = window.setTimeout(function () {
                dom.offersEnd.classList.add('d-none');
                offersEndTimer = null;
            }, 2600);
        }
    };

    const hasOfferCards = function () {
        return !!(dom.offersItems && dom.offersItems.querySelector('.p2p-offer-card'));
    };

    const syncOffersPagingState = function (page, appended) {
        state.offersAppended = state.offersAppended || appended;
        state.offersNextPage = page < state.offersLastPage ? page + 1 : 0;

        if (dom.offersList) {
            dom.offersList.dataset.currentPage = String(page);
            dom.offersList.dataset.nextPage = String(state.offersNextPage);
        }

        toggleOffersEnd(state.offersNextPage === 0 && hasOfferCards());
    };

    const buildOffersUrl = function (page) {
        const url = new URL("{{ route('user.p2p.offers.index') }}", window.location.origin);
        const params = new URLSearchParams(window.location.search);
        params.set('rows', '1');
        params.set('page', String(page));
        url.search = params.toString();

        return url.toString();
    };

    const fillOffersViewport = function () {
        if (!dom.offersList || state.offersLoading || state.offersNextPage === 0) {
            return;
        }

        if (dom.offersList.scrollHeight <= dom.offersList.clientHeight + 40) {
            void loadOffersPage(state.offersNextPage, true);
        }
    };

    const loadOffersPage = function (page, append) {
        if (!dom.offersList || !dom.offersItems || state.offersLoading || page <= 0) {
            return Promise.resolve(false);
        }

        state.offersLoading = true;
        toggleOffersLoader(true);

        return fetch(buildOffersUrl(page), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                const markup = String(html || '').trim();

                if (markup === '') {
                    state.offersNextPage = 0;
                    toggleOffersEnd(hasOfferCards());
                    return false;
                }

                if (append && markup.indexOf('p2p-empty') !== -1) {
                    state.offersNextPage = 0;
                    toggleOffersEnd(hasOfferCards());
                    return false;
                }

                if (append) {
                    dom.offersItems.insertAdjacentHTML('beforeend', markup);
                } else {
                    dom.offersItems.innerHTML = markup;
                }

                initPopovers();
                syncOffersPagingState(page, append || page > 1);

                return true;
            })
            .catch(function () {
                return false;
            })
            .finally(function () {
                state.offersLoading = false;
                toggleOffersLoader(false);
                fillOffersViewport();
            });
    };

    const handleOffersScroll = function () {
        if (!dom.offersList) {
            return;
        }

        const nearBottom = dom.offersList.scrollTop + dom.offersList.clientHeight >= dom.offersList.scrollHeight - 120;

        if (state.offersNextPage === 0) {
            if (nearBottom && hasOfferCards()) {
                toggleOffersEnd(true);
            }
            return;
        }

        if (state.offersLoading) {
            return;
        }

        if (nearBottom) {
            void loadOffersPage(state.offersNextPage, true);
        }
    };

    const refreshOffers = function () {
        if (!dom.offersList || !dom.offersItems || state.offersLoading || state.offersAppended) {
            return;
        }

        if (dom.offersList.scrollTop > 8) {
            return;
        }

        void loadOffersPage(1, false);
    };

    const formatNumber = function (value, maxDecimals) {
        const v = Number(value);
        if (!Number.isFinite(v)) {
            return '-';
        }
        return v.toLocaleString(undefined, { maximumFractionDigits: maxDecimals });
    };

    const formatFiat = function (value) {
        const v = Number(value);
        if (!Number.isFinite(v)) {
            return '-';
        }
        return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    const escapeHtml = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const decodeEntities = function (str) {
        return String(str || '')
            .replace(/&quot;/g, '"')
            .replace(/&#34;/g, '"')
            .replace(/&#039;/g, "'")
            .replace(/&apos;/g, "'")
            .replace(/&amp;/g, '&');
    };

    // Tint the modal chrome (header gradient, avatar ring, CTA gradient) to the
    // trading-convention colour for the active side: buy = green, sell = red.
    const setModalSide = function (action) {
        if (!dom.modalContent) {
            return;
        }

        dom.modalContent.classList.remove('p2p-order-modal--buy', 'p2p-order-modal--sell');
        dom.modalContent.classList.add(action === 'sell' ? 'p2p-order-modal--sell' : 'p2p-order-modal--buy');
    };

    const setCoinBadge = function (currency, flag) {
        if (!dom.coinBadge) {
            return;
        }

        dom.coinBadge.className = 'p2p-order-coin-badge';
        dom.coinBadge.innerHTML = '';

        const code = String(currency || '').trim();
        if (code === '' || code === '-') {
            dom.coinBadge.classList.add('d-none');
            return;
        }

        if (flag) {
            const img = document.createElement('img');
            img.src = flag;
            img.alt = code;
            img.loading = 'lazy';
            dom.coinBadge.appendChild(img);
        } else {
            dom.coinBadge.classList.add('p2p-order-coin-badge--' + code.toLowerCase());
            dom.coinBadge.textContent = code.substring(0, 1).toUpperCase();
        }

        dom.coinBadge.classList.remove('d-none');
    };

    const setRateTrend = function (direction, text, title) {
        if (!dom.rateTrend) {
            return;
        }

        dom.rateTrend.className = 'p2p-order-rate-trend';
        dom.rateTrend.innerHTML = '';

        const dir = String(direction || '').trim();
        if (dir === '' || !text) {
            dom.rateTrend.classList.add('d-none');
            dom.rateTrend.removeAttribute('title');
            return;
        }

        dom.rateTrend.classList.add('p2p-order-rate-trend--' + dir);

        const icon = document.createElement('i');
        icon.className = 'fas ' + (dir === 'up' ? 'fa-caret-up' : (dir === 'down' ? 'fa-caret-down' : 'fa-minus'));
        icon.setAttribute('aria-hidden', 'true');
        dom.rateTrend.appendChild(icon);
        dom.rateTrend.appendChild(document.createTextNode(' ' + text + ' ' + lang.vsMarket));

        if (title) {
            dom.rateTrend.title = title;
        } else {
            dom.rateTrend.removeAttribute('title');
        }

        dom.rateTrend.classList.remove('d-none');
    };

    const updateAmountValidation = function () {
        if (!dom.amountHint) {
            return;
        }

        const inputWrap = dom.amount.closest('.p2p-order-input');
        const clearState = function () {
            dom.amountHint.textContent = '';
            dom.amountHint.classList.remove('is-valid', 'is-invalid');
            if (inputWrap) {
                inputWrap.classList.remove('is-invalid');
            }
        };

        const raw = String(dom.amount.value || '').trim();
        if (raw === '') {
            clearState();
            return;
        }

        const amount = Number(raw);
        if (!Number.isFinite(amount) || amount <= 0) {
            clearState();
            return;
        }

        const min = Number(state.minAmount);
        const max = state.maxAmount;

        const fail = function (message) {
            dom.amountHint.textContent = message;
            dom.amountHint.classList.add('is-invalid');
            dom.amountHint.classList.remove('is-valid');
            if (inputWrap) {
                inputWrap.classList.add('is-invalid');
            }
        };

        if (Number.isFinite(min) && min > 0 && amount < min) {
            fail(lang.minimumIs + ' ' + formatNumber(min, 8) + ' ' + state.currency);
            return;
        }

        if (max !== null && Number.isFinite(max) && amount > max) {
            fail(lang.maximumIs + ' ' + formatNumber(max, 8) + ' ' + state.currency);
            return;
        }

        dom.amountHint.textContent = '✓ ' + lang.withinLimit;
        dom.amountHint.classList.add('is-valid');
        dom.amountHint.classList.remove('is-invalid');
        if (inputWrap) {
            inputWrap.classList.remove('is-invalid');
        }
    };

    const applyQuickAmount = function (pct) {
        const max = state.maxAmount;
        if (max === null || !Number.isFinite(max) || max <= 0) {
            return;
        }

        const decimals = state.currency === 'USDT' ? 2 : 6;
        const value = Number((max * (pct / 100)).toFixed(decimals));

        dom.amount.value = String(value);
        updateTotals();
        updateAmountValidation();
        updateSubmitAvailability();
    };

    const flashCopied = function (btn) {
        const icon = btn.querySelector('i');
        if (!icon) {
            return;
        }

        const previous = icon.className;
        icon.className = 'fas fa-check';
        btn.classList.add('is-copied');
        window.setTimeout(function () {
            icon.className = previous;
            btn.classList.remove('is-copied');
        }, 1200);
    };

    const fallbackCopy = function (text, done) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand('copy');
        } catch (_) {}
        document.body.removeChild(textarea);
        if (typeof done === 'function') {
            done();
        }
    };

    const copyText = function (text, btn) {
        if (!text) {
            return;
        }

        const done = function () {
            if (btn) {
                flashCopied(btn);
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () {
                fallbackCopy(text, done);
            });
        } else {
            fallbackCopy(text, done);
        }
    };

    const renderAcceptedMethods = function (methods) {
        if (!dom.acceptedMethodsWrap || !dom.acceptedMethods) {
            return;
        }

        dom.acceptedMethods.innerHTML = '';

        if (!Array.isArray(methods) || methods.length === 0) {
            dom.acceptedMethodsWrap.classList.add('d-none');
            return;
        }

        dom.acceptedMethodsWrap.classList.remove('d-none');

        const savedMethodIds = (Array.isArray(userPaymentAccounts) ? userPaymentAccounts : [])
            .map(function (account) {
                return Number(account && account.payment_method_id ? account.payment_method_id : 0);
            });

        methods.forEach(function (method) {
            const methodId = Number(method && method.id ? method.id : 0);
            const hasAccount = methodId > 0 && savedMethodIds.indexOf(methodId) !== -1;

            // Ready methods are a static chip with a check; methods the viewer has
            // not saved yet become a link to the add-account page with the method
            // preselected, so they can create it without losing their place.
            const pill = document.createElement(hasAccount ? 'span' : 'a');
            pill.className = 'p2p-payment-pill p2p-payment-method-chip '
                + (hasAccount ? 'p2p-payment-method-chip--ready' : 'p2p-payment-method-chip--add');

            if (hasAccount) {
                pill.setAttribute('title', readyAccountChipTitle);
            } else if (methodId > 0) {
                pill.href = addPaymentAccountUrlTemplate.replace('__METHOD_ID__', String(methodId));
                pill.setAttribute('title', addAccountChipTitle);
            }

            if (method && method.logo) {
                const img = document.createElement('img');
                img.className = 'p2p-payment-pill__logo';
                img.src = method.logo;
                img.alt = method.name || '';
                img.loading = 'lazy';
                pill.appendChild(img);
            } else {
                const fallback = document.createElement('span');
                fallback.className = 'p2p-payment-pill__fallback';
                fallback.textContent = method && method.name ? String(method.name).trim().substring(0, 1).toUpperCase() : 'P';
                pill.appendChild(fallback);
            }

            const text = document.createElement('span');
            text.className = 'p2p-payment-pill__text';
            text.textContent = method && method.name ? method.name : '';
            pill.appendChild(text);

            const statusIcon = document.createElement('i');
            statusIcon.className = 'p2p-payment-method-chip__status fas '
                + (hasAccount ? 'fa-circle-check' : 'fa-circle-plus');
            statusIcon.setAttribute('aria-hidden', 'true');
            pill.appendChild(statusIcon);

            dom.acceptedMethods.appendChild(pill);
        });
    };

    const getEligiblePaymentAccounts = function () {
        const accounts = Array.isArray(userPaymentAccounts) ? userPaymentAccounts.slice() : [];
        const acceptedMethodIds = Array.isArray(state.acceptedPaymentMethods)
            ? state.acceptedPaymentMethods.map(function (method) {
                return Number(method && method.id ? method.id : 0);
            }).filter(function (id) {
                return Number.isFinite(id) && id > 0;
            })
            : [];

        if (acceptedMethodIds.length === 0) {
            return accounts;
        }

        return accounts.filter(function (account) {
            return acceptedMethodIds.indexOf(Number(account.payment_method_id || 0)) !== -1;
        });
    };

    const renderPaymentSelect = function (accounts) {
        dom.paymentSelect.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = lang.selectPaymentAccount;
        dom.paymentSelect.appendChild(opt0);

        if (!Array.isArray(accounts) || accounts.length === 0) {
            dom.paymentSelect.disabled = true;
            return;
        }

        dom.paymentSelect.disabled = false;
        accounts.forEach(function (account) {
            const opt = document.createElement('option');
            opt.value = account && account.id ? String(account.id) : '';
            opt.textContent = [
                account && account.payment_method_name ? account.payment_method_name : '',
                account && account.account_label ? account.account_label : '',
                account && account.display_value ? '(' + account.display_value + ')' : ''
            ].filter(function (part) {
                return String(part || '').trim() !== '';
            }).join(' - ');
            dom.paymentSelect.appendChild(opt);
        });
    };

    const getPaymentAccountById = function (id) {
        const accounts = Array.isArray(state.eligiblePaymentAccounts) ? state.eligiblePaymentAccounts : [];
        return accounts.find(function (account) {
            return Number(account.id) === Number(id);
        }) || null;
    };

    const renderTerms = function () {
        if (!dom.termsList) {
            return;
        }

        const items = [];
        const termsText = typeof state.terms === 'string' ? state.terms.trim() : '';

        if (termsText !== '') {
            termsText.split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean).forEach(function (l) {
                items.push(l);
            });
        } else {
            items.push(lang.defaultTermSavedAccount);
            items.push(lang.defaultTermNoNotes);
            items.push(lang.defaultTermConfirmAfterReceipt);
            items.push(lang.defaultTermLocalLaw);
        }

        dom.termsList.innerHTML = '';
        items.forEach(function (txt) {
            const li = document.createElement('li');
            li.textContent = txt;
            dom.termsList.appendChild(li);
        });
    };

    const isAmountWithinLimits = function () {
        const raw = String(dom.amount.value || '').trim();
        const amount = Number(raw);

        if (raw === '' || !Number.isFinite(amount) || amount <= 0) {
            return false;
        }

        const min = Number(state.minAmount);
        if (Number.isFinite(min) && min > 0 && amount < min) {
            return false;
        }

        if (state.maxAmount !== null && Number.isFinite(state.maxAmount) && amount > state.maxAmount) {
            return false;
        }

        return true;
    };

    // Reference behaviour: the confirm button stays disabled and its label walks
    // the user through what is missing — amount → account → terms → confirm.
    const updateSubmitAvailability = function () {
        if (!dom.submitBtn || !dom.submitText) {
            return;
        }

        const amountOk = isAmountWithinLimits();
        const hasAccount = !!state.selectedPaymentAccountId;
        const agreed = dom.termsAgree ? dom.termsAgree.checked : false;

        dom.submitBtn.disabled = !(amountOk && hasAccount && agreed);
        dom.submitText.textContent = !amountOk
            ? lang.enterAmount
            : (!hasAccount
                ? lang.selectPaymentAccount
                : (!agreed ? lang.agreeTerms : state.actionLabel + ' ' + lang.securely));
    };

    const setSelectedPaymentAccount = function (id) {
        const account = id ? getPaymentAccountById(id) : null;
        state.selectedPaymentAccountId = account ? Number(account.id) : null;

        if (dom.paymentAccountId) {
            dom.paymentAccountId.value = state.selectedPaymentAccountId ? String(state.selectedPaymentAccountId) : '';
        }

        if (dom.paymentSelect) {
            dom.paymentSelect.value = state.selectedPaymentAccountId ? String(state.selectedPaymentAccountId) : '';
            dom.paymentSelect.classList.toggle('has-value', !!account);
        }

        if (dom.paymentInstructions) {
            if (account) {
                const lines = [];
                const details = Array.isArray(account.details) ? account.details : [];

                const isCopyableDetail = function (label) {
                    const normalized = String(label || '').toLowerCase();

                    return normalized.indexOf('number') !== -1
                        || normalized.indexOf(' id') !== -1
                        || normalized.endsWith('id')
                        || normalized.indexOf('reference') !== -1
                        || normalized.indexOf('note') !== -1
                        || normalized.indexOf('email') !== -1
                        || normalized.indexOf('phone') !== -1
                        || normalized.indexOf('mobile') !== -1;
                };

                const detailRow = function (label, value, withCopy) {
                    return '<div class="p2p-order-instructions__row">'
                        + '<span class="p2p-order-instructions__label">' + escapeHtml(label) + '</span>'
                        + '<span class="p2p-order-instructions__value">' + escapeHtml(value) + '</span>'
                        + (withCopy
                            ? '<button type="button" class="p2p-order-copy" data-copy="' + escapeHtml(value) + '" title="' + escapeHtml(lang.copied) + '" aria-label="' + escapeHtml(lang.copied) + '"><i class="fas fa-copy" aria-hidden="true"></i></button>'
                            : '')
                        + '</div>';
                };

                if (String(account.payment_method_name || '').trim() !== '') {
                    lines.push(detailRow(lang.method, account.payment_method_name, false));
                }

                if (String(account.account_label || '').trim() !== '') {
                    lines.push(detailRow(lang.account, account.account_label, false));
                }

                details.forEach(function (detail) {
                    if (!detail || String(detail.value || '').trim() === '') {
                        return;
                    }

                    lines.push(detailRow(detail.label || '', String(detail.value || ''), isCopyableDetail(detail.label)));
                });

                if (String(account.method_instructions || '').trim() !== '') {
                    lines.push(
                        '<div class="p2p-order-instructions__note">'
                        + '<span>' + escapeHtml(lang.paymentInstructions) + '</span>'
                        + '<p>' + escapeHtml(account.method_instructions).replace(/\n/g, '<br>') + '</p>'
                        + '</div>'
                    );
                }

                dom.paymentInstructions.innerHTML = lines.join('');
                dom.paymentInstructions.classList.remove('d-none');
            } else {
                if (String(state.emptyPaymentAccountMessage || '').trim() !== '') {
                    dom.paymentInstructions.textContent = state.emptyPaymentAccountMessage;
                    dom.paymentInstructions.classList.remove('d-none');
                } else {
                    dom.paymentInstructions.textContent = '';
                    dom.paymentInstructions.classList.add('d-none');
                }
            }
        }

        renderTerms();
        updateSubmitAvailability();
    };

    const updateTotals = function (skipFiatSync) {
        const raw = String(dom.amount.value || '').trim();
        const amount = Number(raw);
        const price = Number(state.price);
        const hasAmount = raw !== '' && Number.isFinite(amount);
        const fiatValue = (hasAmount ? amount : 0) * (Number.isFinite(price) ? price : 0);

        if (dom.fiatInput && !skipFiatSync) {
            dom.fiatInput.value = hasAmount ? fiatValue.toFixed(2) : '';
        }
        if (dom.fiatCcy) {
            dom.fiatCcy.textContent = state.fiatCurrency;
        }

        if (!hasAmount) {
            dom.pay.textContent = '-';
            dom.payCcy.textContent = state.action === 'sell' ? state.currency : state.fiatCurrency;
            dom.receive.textContent = '-';
            dom.receiveCcy.textContent = state.action === 'sell' ? state.fiatCurrency : state.currency;
        } else if (state.action === 'sell') {
            dom.pay.textContent = formatNumber(amount, 8);
            dom.payCcy.textContent = state.currency;
            dom.receive.textContent = formatFiat(fiatValue);
            dom.receiveCcy.textContent = state.fiatCurrency;
        } else {
            dom.pay.textContent = formatFiat(fiatValue);
            dom.payCcy.textContent = state.fiatCurrency;
            dom.receive.textContent = formatNumber(amount, 8);
            dom.receiveCcy.textContent = state.currency;
        }

        if (dom.feeValue) {
            dom.feeValue.textContent = formatFiat(0);
        }
        if (dom.feeValueCcy) {
            dom.feeValueCcy.textContent = state.fiatCurrency;
        }
    };

    dom.amount.addEventListener('input', function () {
        updateTotals();
        updateAmountValidation();
        updateSubmitAvailability();
    });

    if (dom.fiatInput) {
        dom.fiatInput.addEventListener('input', function () {
            const fiat = Number(String(dom.fiatInput.value || '').trim());
            const price = Number(state.price);

            if (Number.isFinite(fiat) && fiat > 0 && Number.isFinite(price) && price > 0) {
                const decimals = state.currency === 'USDT' ? 2 : 6;
                dom.amount.value = String(+(fiat / price).toFixed(decimals));
            } else {
                dom.amount.value = '';
            }

            updateTotals(true);
            updateAmountValidation();
            updateSubmitAvailability();
        });
    }

    if (dom.swapBtn && dom.convertRow) {
        dom.swapBtn.addEventListener('click', function () {
            dom.convertRow.classList.toggle('is-swapped');
        });
    }

    if (dom.termsAgree) {
        dom.termsAgree.addEventListener('change', function () {
            updateSubmitAvailability();
        });
    }

    if (dom.paymentSelect) {
        dom.paymentSelect.addEventListener('change', function () {
            const id = dom.paymentSelect.value ? Number(dom.paymentSelect.value) : null;
            setSelectedPaymentAccount(id);
        });
    }

    if (dom.quickChips) {
        dom.quickChips.addEventListener('click', function (event) {
            const btn = event.target.closest('.p2p-order-quickchip');
            if (!btn) {
                return;
            }

            event.preventDefault();
            const pct = Number(btn.getAttribute('data-quick'));
            if (Number.isFinite(pct)) {
                applyQuickAmount(pct);
            }
        });
    }

    if (dom.paymentInstructions) {
        dom.paymentInstructions.addEventListener('click', function (event) {
            const btn = event.target.closest('.p2p-order-copy');
            if (!btn) {
                return;
            }

            event.preventDefault();
            copyText(btn.getAttribute('data-copy') || '', btn);
        });
    }

    modalEl.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) {
            return;
        }

        const offerId = btn.getAttribute('data-offer-id') || '';
        const min = btn.getAttribute('data-min') || '0';
        const max = btn.getAttribute('data-max') || '';
        const price = btn.getAttribute('data-price') || '0';
        const currency = btn.getAttribute('data-currency') || '-';
        const fiatCurrency = btn.getAttribute('data-fiat-currency') || '-';
        const action = btn.getAttribute('data-action') || 'buy';
        const actionLabel = btn.getAttribute('data-action-label') || "{{ __('Confirm') }}";
        const availableText = btn.getAttribute('data-available-text') || '-';
        const limitText = btn.getAttribute('data-limit-text') || '-';
        const userName = btn.getAttribute('data-user-name') || '-';
        const userInitials = btn.getAttribute('data-user-initials') || '-';
        const userAvatar = btn.getAttribute('data-user-avatar') || '';
        const userVerified = btn.getAttribute('data-user-verified') === '1';
        const completionRate = btn.getAttribute('data-completion-rate') || '-';
        const totalTrades = btn.getAttribute('data-total-trades') || '-';
        const paymentWindow = btn.getAttribute('data-payment-window') || '0';
        const advertiserUrl = btn.getAttribute('data-advertiser-url') || '#';
        const currencyFlag = btn.getAttribute('data-currency-flag') || '';
        const trendDirection = btn.getAttribute('data-trend-direction') || '';
        const trendText = btn.getAttribute('data-trend-text') || '';
        const trendTitle = btn.getAttribute('data-trend-title') || '';
        const paymentMethodsRaw = btn.getAttribute('data-payment-methods') || '[]';
        const termsRaw = btn.getAttribute('data-terms') || 'null';

        let paymentMethods = [];
        try {
            paymentMethods = JSON.parse(decodeEntities(paymentMethodsRaw));
        } catch (_) {
            paymentMethods = [];
        }

        let terms = null;
        try {
            terms = JSON.parse(decodeEntities(termsRaw));
        } catch (_) {
            terms = null;
        }

        state.price = Number(price);
        state.currency = currency;
        state.fiatCurrency = fiatCurrency;
        state.action = action;
        state.minAmount = parseFloat(min);
        state.maxAmount = max !== '' ? parseFloat(max) : null;
        state.acceptedPaymentMethods = Array.isArray(paymentMethods) ? paymentMethods : [];
        state.terms = (typeof terms === 'string') ? terms : null;
        state.eligiblePaymentAccounts = getEligiblePaymentAccounts();
        state.selectedPaymentAccountId = null;
        state.emptyPaymentAccountMessage = state.eligiblePaymentAccounts.length === 0
            ? (state.acceptedPaymentMethods.length > 0
                ? lang.noMatchingAccount
                : lang.needSavedAccount)
            : '';

        dom.offerId.value = offerId;

        state.actionLabel = actionLabel;

        dom.title.textContent = actionLabel;
        dom.amountTitle.textContent = action === 'sell' ? lang.amountToSell : lang.amountToBuy;
        dom.submitBtn.classList.remove('p2p-order-submit--buy', 'p2p-order-submit--sell');
        dom.submitBtn.classList.add(action === 'sell' ? 'p2p-order-submit--sell' : 'p2p-order-submit--buy');

        if (dom.sideBadge) {
            dom.sideBadge.textContent = action === 'sell' ? lang.sell : lang.buy;
            dom.sideBadge.classList.remove('p2p-order-side-badge--buy', 'p2p-order-side-badge--sell');
            dom.sideBadge.classList.add(action === 'sell' ? 'p2p-order-side-badge--sell' : 'p2p-order-side-badge--buy');
        }

        const rateText = `1 ${currency} = ${formatNumber(price, 2)} ${fiatCurrency}`;
        dom.rate.textContent = rateText;
        if (dom.exchangeRate) {
            dom.exchangeRate.textContent = rateText;
        }
        dom.currency.textContent = currency;
        dom.currencyUnit.textContent = currency;
        dom.available.textContent = availableText;
        dom.limit.textContent = limitText;
        dom.limitCcy.textContent = currency;

        dom.advertiserLink.textContent = userName;
        dom.advertiserLink.href = advertiserUrl;
        dom.completion.textContent = completionRate;
        dom.trades.textContent = totalTrades;
        if (dom.rating) {
            dom.rating.textContent = '-';
        }
        if (dom.releaseTime) {
            dom.releaseTime.textContent = paymentWindow ? `~${paymentWindow} ${lang.min}` : '-';
        }

        if (userVerified) {
            dom.verified.classList.remove('d-none');
        } else {
            dom.verified.classList.add('d-none');
        }

        if (userAvatar) {
            dom.avatarImg.src = userAvatar;
            dom.avatarImg.alt = userName;
            dom.avatarImg.classList.remove('d-none');
            dom.avatarText.classList.add('d-none');
        } else {
            dom.avatarImg.classList.add('d-none');
            dom.avatarText.classList.remove('d-none');
            dom.avatarText.textContent = userInitials;
        }

        if (dom.termsAgree) {
            dom.termsAgree.checked = false;
        }

        if (dom.paymentCardTitle) {
            dom.paymentCardTitle.textContent = action === 'sell'
                ? lang.yourReceivingAccount
                : lang.yourPaymentAccount;
        }

        if (dom.paymentCardBadge) {
            dom.paymentCardBadge.textContent = action === 'sell'
                ? lang.receivingAccount
                : lang.buyerAccount;
        }

        if (dom.paymentFlowText) {
            dom.paymentFlowText.textContent = action === 'sell'
                ? lang.receivingAccountHint
                : lang.buyerAccountHint;
        }

        dom.amount.min = min || 0;
        if (max) {
            dom.amount.max = max;
        } else {
            dom.amount.removeAttribute('max');
        }

        dom.amount.value = '';
        if (dom.fiatInput) {
            dom.fiatInput.value = '';
        }
        if (dom.convertRow) {
            dom.convertRow.classList.remove('is-swapped');
        }
        renderAcceptedMethods(state.acceptedPaymentMethods);
        renderPaymentSelect(state.eligiblePaymentAccounts);
        setSelectedPaymentAccount(null);
        updateTotals();

        setModalSide(action);
        setCoinBadge(currency, currencyFlag);
        setRateTrend(trendDirection, trendText, trendTitle);

        if (dom.quickChips) {
            const hasMax = state.maxAmount !== null && Number.isFinite(state.maxAmount) && state.maxAmount > 0;
            dom.quickChips.classList.toggle('d-none', !hasMax);
        }

        if (dom.feeFree) {
            dom.feeFree.classList.remove('d-none');
        }

        updateAmountValidation();
        updateSubmitAvailability();
    });

    const renderResumeBanner = function () {
        const banner = document.getElementById('p2pResumeBanner');
        const link = document.getElementById('p2pResumeLink');
        const orderText = document.getElementById('p2pResumeOrderText');
        const statusEl = document.getElementById('p2pResumeStatus');
        const updatedEl = document.getElementById('p2pResumeUpdated');
        const metaEl = document.getElementById('p2pResumeMeta');

        if (!banner || !link || !orderText || !metaEl) {
            return;
        }

        banner.classList.add('d-none');
        metaEl.innerHTML = '';

        if (statusEl) {
            statusEl.textContent = '';
            statusEl.classList.add('d-none');
            statusEl.classList.remove('is-pending', 'is-paid', 'is-disputed');
        }

        if (updatedEl) {
            updatedEl.textContent = '';
            updatedEl.classList.add('d-none');
        }

        const activeStatuses = ['PENDING', 'PAID', 'DISPUTED'];
        const statusClassMap = {
            PENDING: 'is-pending',
            PAID: 'is-paid',
            DISPUTED: 'is-disputed'
        };
        const statusLabelMap = {
            PENDING: lang.pending,
            PAID: lang.paid,
            DISPUTED: lang.disputed
        };

        const formatNumber = function (value, maxDecimals) {
            const num = Number(value);
            if (!Number.isFinite(num)) {
                return '';
            }

            return num.toLocaleString(undefined, {
                maximumFractionDigits: maxDecimals
            });
        };

        const formatUpdated = function (timestamp) {
            const value = Number(timestamp);
            if (!Number.isFinite(value) || value <= 0) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            return lang.updated + ': ' + date.toLocaleString();
        };

        const appendMetaChip = function (iconClass, text) {
            if (!text) {
                return;
            }

            const chip = document.createElement('span');
            chip.className = 'p2p-resume-chip';

            const icon = document.createElement('i');
            icon.className = iconClass;
            icon.setAttribute('aria-hidden', 'true');

            const label = document.createElement('span');
            label.textContent = text;

            chip.appendChild(icon);
            chip.appendChild(label);
            metaEl.appendChild(chip);
        };

        try {
            const item = localStorage.getItem('p2p_last_order');
            if (!item) {
                return;
            }

            const data = JSON.parse(item);
            if (!data || !data.id || !data.url) {
                return;
            }

            // localStorage is shared across accounts on the same browser. Only
            // surface the resume banner to the user who actually viewed the
            // order — anyone else would hit the order policy's 403.
            if (!data.user_id || Number(data.user_id) !== currentUserId) {
                return;
            }

            const statusValue = String(data.status || '').toUpperCase();
            if (statusValue !== '' && activeStatuses.indexOf(statusValue) === -1) {
                return;
            }

            const updatedAt = Number(data.updated_at || 0);
            if (Number.isFinite(updatedAt) && updatedAt > 0 && (Date.now() - updatedAt) > (7 * 24 * 60 * 60 * 1000)) {
                return;
            }

            link.href = String(data.url);
            orderText.textContent = lang.order + ' #' + String(data.id);

            if (statusEl) {
                const statusLabel = data.status_label ? String(data.status_label) : (statusLabelMap[statusValue] || statusValue);
                if (statusLabel !== '') {
                    statusEl.textContent = statusLabel;
                    statusEl.classList.remove('d-none', 'is-pending', 'is-paid', 'is-disputed');
                    if (statusClassMap[statusValue]) {
                        statusEl.classList.add(statusClassMap[statusValue]);
                    }
                } else {
                    statusEl.classList.add('d-none');
                    statusEl.textContent = '';
                }
            }

            if (updatedEl) {
                const updatedText = formatUpdated(data.updated_at);
                updatedEl.textContent = updatedText;
                updatedEl.classList.toggle('d-none', updatedText === '');
            }

            const sideLabel = data.side_label ? String(data.side_label) : String(data.side_value || '').toUpperCase();
            appendMetaChip('fas fa-exchange-alt', sideLabel);

            const amountText = formatNumber(data.amount, 8);
            const currencyText = String(data.currency || '').toUpperCase();
            if (amountText !== '' && currencyText !== '') {
                appendMetaChip('fas fa-coins', amountText + ' ' + currencyText);
            }

            const rateText = formatNumber(data.price, 2);
            const fiatText = String(data.fiat_currency || '').toUpperCase();
            if (rateText !== '' && fiatText !== '' && currencyText !== '') {
                appendMetaChip('fas fa-chart-line', '1 ' + currencyText + ' ~ ' + rateText + ' ' + fiatText);
            }

            const paymentWindow = Number(data.payment_window_minutes || 0);
            if (Number.isFinite(paymentWindow) && paymentWindow > 0) {
                appendMetaChip('fas fa-hourglass-half', lang.release + ' ~ ' + paymentWindow + ' ' + lang.min);
            }

            banner.classList.remove('d-none');
        } catch (_) {}
    };

    renderResumeBanner();
    window.addEventListener('focus', renderResumeBanner);
    window.addEventListener('storage', function (event) {
        if (event.key === 'p2p_last_order') {
            renderResumeBanner();
        }
    });

    initPopovers();

    if (dom.offersPagination) {
        dom.offersPagination.classList.add('d-none');
    }

    if (dom.offersList) {
        dom.offersList.addEventListener('scroll', handleOffersScroll);
        fillOffersViewport();
        setInterval(refreshOffers, 10000);
    }
});
</script>
