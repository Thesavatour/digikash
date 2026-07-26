"use strict";

/* =========================================================================
 * Dashboard Main Script
 * - UI interactions (navbar, sidebar, slider, tooltips, clipboard)
 * - Desktop scroll manager (Facebook-style smooth scroll routing)
 * ========================================================================= */

(function ($) {

    /* -----------------------------------------------------------------
     * § UI Interactions
     * ----------------------------------------------------------------- */

    $(document).ready(function () {

        /* Navbar Dropdown Link Prevent */
        $(document).on('click', '.navbar-area .navbar-nav li.menu-item-has-children > a', function (e) {
            e.preventDefault();
        });

        /* Search Popup */
        var $bodyOverlay = $('#body-overlay');
        var $searchPopup = $('#td-search-popup');
        var $searchBtn   = $('.search-bar-btn');
        var $sidebarMenu = $('#sidebar-menu');

        $(document).on('click', '#body-overlay', function (e) {
            e.preventDefault();
            $bodyOverlay.removeClass('active');
            $searchPopup.removeClass('active');
            $sidebarMenu.removeClass('active');
        });

        $(document).on('click', '.search-bar-btn', function (e) {
            e.preventDefault();
            $searchPopup.toggleClass('active');
            $searchBtn.toggleClass('active');
        });

        /* Mobile Sidebar Menu */
        $(document).on('click', '.sidebar-menu-close', function (e) {
            e.preventDefault();
            $bodyOverlay.removeClass('active');
            $sidebarMenu.removeClass('active');
        });

        $(document).on('click', '#navigation-button', function (e) {
            e.preventDefault();
            $sidebarMenu.addClass('active');
            $bodyOverlay.addClass('active');
        });

        /* Wallet Slider */
        if ($('.walet-slider').length) {
            $('.walet-slider').each(function () {
                var $slider = $(this);
                var isSidebarWallet = $slider.is('[data-sidebar-wallet-slider]');

                $slider.owlCarousel({
                    nav: true,
                    margin: 5,
                    dots: isSidebarWallet,
                    smartSpeed: 1500,
                    items: 1,
                    loop: false,
                    autoplay: false,
                    navText: [
                        '<i class="fa fa-angle-left mx-2"></i>',
                        '<i class="fa fa-angle-right"></i>'
                    ],
                    responsive: {
                        0:   { items: 1 },
                        576: { items: isSidebarWallet ? 1 : 2 },
                        992: { items: 1 }
                    }
                });
            });
        }

        /* Wallet Currency Preview */
        $(document).on('change', '[data-wallet-currency-select]', function () {
            var $select = $(this);
            var currencyId = $select.val();
            var urlTemplate = $select.data('currency-info-url') || '';
            var loadingText = $select.data('loading-text') || 'Loading...';
            var $preview = $select.closest('form').find('[data-wallet-currency-preview]');

            if (!currencyId || !urlTemplate || !$preview.length) {
                return;
            }

            $preview.html(
                '<div class="d-flex align-items-center justify-content-center">' +
                    '<div class="spinner-border text-primary" role="status">' +
                        '<span class="visually-hidden">' + loadingText + '</span>' +
                    '</div>' +
                '</div>'
            );

            $.get(urlTemplate.replace('__currency_id__', currencyId), function (data) {
                $preview.html(data);
            });
        });

        /* Premium dashboard selects */
        var premiumSelectId = 0;

        function getPremiumSelectIcon($select) {
            if ($select.hasClass('wallet-select') || $select.hasClass('source-wallet-select') || $select.hasClass('destination-wallet-select')) {
                return 'fa-wallet';
            }

            if ($select.is('[data-wallet-currency-select]') || ($select.attr('name') || '').indexOf('currency') !== -1 || ($select.attr('id') || '').toLowerCase().indexOf('currency') !== -1) {
                return 'fa-coins';
            }

            if (($select.attr('name') || '').indexOf('wallet') !== -1 || ($select.attr('id') || '').toLowerCase().indexOf('wallet') !== -1) {
                return 'fa-wallet';
            }

            if ($select.hasClass('deposit-method-list')) {
                return 'fa-credit-card';
            }

            if (($select.attr('name') || '').indexOf('payment') !== -1 || ($select.attr('id') || '').toLowerCase().indexOf('payment') !== -1) {
                return 'fa-credit-card';
            }

            if ($select.hasClass('withdraw-account-select')) {
                return 'fa-building-columns';
            }

            if (($select.attr('name') || '').indexOf('country') !== -1 || ($select.attr('id') || '').toLowerCase().indexOf('country') !== -1) {
                return 'fa-globe';
            }

            return 'fa-layer-group';
        }

        function getPremiumSelectList($control) {
            var listId = $control.data('list-id');

            if (!listId) {
                return $control.find('.dk-premium-select__list');
            }

            return $('#' + $.escapeSelector(listId));
        }

        function getPremiumSelectControlFromOption($option) {
            var listId = $option.closest('.dk-premium-select__list').attr('id');

            if (!listId) {
                return $option.closest('.dk-premium-select');
            }

            return $('.dk-premium-select').filter(function () {
                return $(this).data('list-id') === listId;
            }).first();
        }

        function getPremiumSelectCurrent($select) {
            var option = $select.find('option:selected')[0] || $select.find('option').not(':disabled').first()[0] || $select.find('option').first()[0];
            var text = option ? $.trim($(option).text()) : '';

            return text || $select.data('placeholder') || 'Select option';
        }

        function closePremiumSelects($except) {
            var $selects = $('.dk-premium-select.is-open');

            if ($except) {
                $selects = $selects.not($except);
            }

            $selects.each(function () {
                var $control = $(this);

                dockPremiumSelectList($control);

                $control
                    .removeClass('is-open')
                    .find('.dk-premium-select__button')
                    .attr('aria-expanded', 'false');
            });
        }

        function syncPremiumSelect($select) {
            var $control = $select.next('.dk-premium-select');

            if (!$control.length) {
                return;
            }

            var current = getPremiumSelectCurrent($select);
            var selectedIndex = $select.prop('selectedIndex');
            var $list = getPremiumSelectList($control);

            $control.find('.dk-premium-select__current').text(current);
            $list.find('.dk-premium-select__option').each(function () {
                var $option = $(this);
                var isSelected = parseInt($option.attr('data-option-index'), 10) === selectedIndex;

                $option
                    .toggleClass('is-selected', isSelected)
                    .attr('aria-selected', isSelected ? 'true' : 'false');
            });
        }

        function getPremiumSelectHeight($select, $control) {
            var currentHeight = $control.length ? parseFloat($control[0].style.getPropertyValue('--dk-premium-select-height')) : 0;
            var selectHeight = $select.is('[data-dk-premium-enhanced]') ? 0 : $select.outerHeight();
            var cssHeight = parseFloat($select.css('height'));

            if (selectHeight && selectHeight > 30) {
                return Math.round(selectHeight) + 'px';
            }

            if (cssHeight && cssHeight > 30) {
                return Math.round(cssHeight) + 'px';
            }

            if (currentHeight && currentHeight > 30) {
                return Math.round(currentHeight) + 'px';
            }

            return $select.hasClass('form-select-sm') ? '40px' : '45px';
        }

        function dockPremiumSelectList($control) {
            var $list = getPremiumSelectList($control);

            if (!$list.length || !$list.hasClass('dk-premium-select__portal')) {
                return;
            }

            $list
                .removeClass('dashboard-user-layout dk-premium-select__portal is-drop-up')
                .removeAttr('style')
                .appendTo($control);
        }

        function positionPremiumSelectList($control) {
            var $button = $control.find('.dk-premium-select__button');
            var $list = getPremiumSelectList($control);

            if (!$button.length || !$list.length) {
                return;
            }

            var rect = $button[0].getBoundingClientRect();
            var gutter = 8;
            var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
            var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            var belowSpace = viewportHeight - rect.bottom - gutter;
            var aboveSpace = rect.top - gutter;
            var opensUp = belowSpace < 160 && aboveSpace > belowSpace;
            var maxHeight = Math.max(120, Math.min(250, (opensUp ? aboveSpace : belowSpace) - gutter));
            var top = opensUp ? Math.max(gutter, rect.top - maxHeight - 6) : rect.bottom + 6;
            var width = Math.min(Math.max(rect.width, 220), Math.max(140, viewportWidth - (gutter * 2)));
            var left = Math.min(Math.max(gutter, rect.left), Math.max(gutter, viewportWidth - width - gutter));

            $list
                .addClass('dashboard-user-layout dk-premium-select__portal')
                .toggleClass('is-drop-up', opensUp)
                .appendTo(document.body)
                .css({
                    left: Math.round(left) + 'px',
                    top: Math.round(top) + 'px',
                    width: Math.round(width) + 'px',
                    maxHeight: Math.round(maxHeight) + 'px'
                });
        }

        function refreshPremiumSelect($select) {
            var $control = $select.next('.dk-premium-select');
            var listId = $control.data('list-id') || ('dk-premium-select-list-' + (++premiumSelectId));
            var icon = getPremiumSelectIcon($select);
            var controlHeight = getPremiumSelectHeight($select, $control);
            var optionsHtml = '';

            $select.find('option').each(function (index) {
                var $option = $(this);
                var text = $.trim($option.text());
                var disabled = $option.is(':disabled');
                var selected = index === $select.prop('selectedIndex');

                if (!text) {
                    return;
                }

                if (disabled) {
                    return;
                }

                optionsHtml += '' +
                    '<button type="button" role="option" class="dk-premium-select__option' +
                        (selected ? ' is-selected' : '') +
                    '" data-option-index="' + index + '"' +
                    ' aria-selected="' + (selected ? 'true' : 'false') + '"' +
                    '>' +
                        '<span class="dk-premium-select__option-icon" aria-hidden="true"><i class="fas ' + icon + '"></i></span>' +
                        '<span class="dk-premium-select__option-text"></span>' +
                        '<span class="dk-premium-select__option-check" aria-hidden="true"><i class="fas fa-check"></i></span>' +
                    '</button>';
            });

            if (!$control.length) {
                $control = $('' +
                    '<div class="dk-premium-select" data-list-id="' + listId + '">' +
                        '<button type="button" class="dk-premium-select__button" aria-haspopup="listbox" aria-expanded="false" aria-controls="' + listId + '">' +
                            '<span class="dk-premium-select__icon" aria-hidden="true"><i class="fas ' + icon + '"></i></span>' +
                            '<span class="dk-premium-select__label">' +
                                '<span class="dk-premium-select__current"></span>' +
                            '</span>' +
                            '<span class="dk-premium-select__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>' +
                        '</button>' +
                        '<div class="dk-premium-select__list" id="' + listId + '" role="listbox"></div>' +
                    '</div>'
                );

                $select.after($control);
            } else {
                $control.data('list-id', listId);
                $control.find('.dk-premium-select__button').attr('aria-controls', listId);
                getPremiumSelectList($control).attr('id', listId);
                $control.find('.dk-premium-select__icon i')
                    .removeClass('fa-wallet fa-coins fa-credit-card fa-building-columns fa-globe fa-layer-group')
                    .addClass(icon);
            }

            $select.addClass('dk-premium-select__native').attr('data-dk-premium-enhanced', 'true');
            $select.parent().addClass('has-dk-premium-select');
            $control
                .css('--dk-premium-select-height', controlHeight)
                .toggleClass('dk-premium-select--sm', $select.hasClass('form-select-sm'))
                .toggleClass('is-invalid', $select.hasClass('is-invalid'))
                .toggleClass('is-disabled', $select.is(':disabled'));
            $control.find('.dk-premium-select__button').prop('disabled', $select.is(':disabled'));
            getPremiumSelectList($control).html(optionsHtml || '<span class="dk-premium-select__empty">No options available</span>');
            getPremiumSelectList($control).find('.dk-premium-select__option-text').each(function () {
                var index = parseInt($(this).closest('.dk-premium-select__option').attr('data-option-index'), 10);
                $(this).text($.trim($select.find('option').eq(index).text()));
            });

            syncPremiumSelect($select);
        }

        function choosePremiumOption($option, keepOpen) {
            var $control = getPremiumSelectControlFromOption($option);
            var $select = $control.prev('select');
            var optionIndex = parseInt($option.attr('data-option-index'), 10);
            var nativeOption = $select.find('option').eq(optionIndex)[0];

            if (!nativeOption || nativeOption.disabled) {
                return;
            }

            nativeOption.selected = true;
            $select.trigger('change');

            if (!keepOpen) {
                closePremiumSelects();
                $control.find('.dk-premium-select__button').focus();
            }
        }

        function observePremiumSelect($select) {
            var select = $select[0];

            if (select.dkPremiumSelectObserver) {
                return;
            }

            if (typeof MutationObserver === 'undefined') {
                return;
            }

            select.dkPremiumSelectObserver = new MutationObserver(function () {
                refreshPremiumSelect($select);
            });

            select.dkPremiumSelectObserver.observe(select, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['disabled', 'label', 'selected', 'value']
            });
        }

        function initPremiumSelects(context) {
            $(context)
                .find('.dashboard-user-layout select')
                .addBack('.dashboard-user-layout select')
                .not('[multiple], [data-no-premium-select], [data-dk-premium-enhanced]')
                .each(function () {
                    var $select = $(this);

                    refreshPremiumSelect($select);
                    observePremiumSelect($select);
                });
        }

        initPremiumSelects(document);

        if (typeof MutationObserver !== 'undefined') {
            var premiumSelectInitTimer = null;
            var premiumSelectRoot = document.querySelector('.dashboard-user-layout');

            if (premiumSelectRoot) {
                new MutationObserver(function (mutations) {
                    var hasNewSelect = mutations.some(function (mutation) {
                        return Array.prototype.some.call(mutation.addedNodes, function (node) {
                            if (node.nodeType !== 1 || node.closest('.dk-premium-select')) {
                                return false;
                            }

                            return node.matches('select') || Boolean(node.querySelector('select'));
                        });
                    });

                    if (!hasNewSelect) {
                        return;
                    }

                    clearTimeout(premiumSelectInitTimer);
                    premiumSelectInitTimer = setTimeout(function () {
                        initPremiumSelects(document);
                    }, 50);
                }).observe(premiumSelectRoot, {
                    childList: true,
                    subtree: true
                });
            }
        }

        $(document).on('ajaxComplete', function () {
            initPremiumSelects(document);
        });

        $(document).on('change', '.dashboard-user-layout select', function () {
            syncPremiumSelect($(this));
        });

        $(document).on('click', '.dashboard-user-layout label[for]', function (event) {
            var $select = $('#' + $.escapeSelector($(this).attr('for')) + '[data-dk-premium-enhanced]');
            var $button = $select.next('.dk-premium-select').find('.dk-premium-select__button');

            if ($button.length) {
                event.preventDefault();
                $button.focus();
            }
        });

        $(document).on('click', '.dk-premium-select__button', function () {
            var $control = $(this).closest('.dk-premium-select');
            var isOpen = $control.hasClass('is-open');

            closePremiumSelects($control);

            $control.toggleClass('is-open', !isOpen);
            $(this).attr('aria-expanded', !isOpen ? 'true' : 'false');

            if (!isOpen) {
                positionPremiumSelectList($control);
            } else {
                dockPremiumSelectList($control);
            }
        });

        $(document).on('click', '.dk-premium-select__option', function () {
            choosePremiumOption($(this), false);
        });

        $(document).on('keydown', '.dk-premium-select__button', function (event) {
            var $button = $(this);
            var $control = $button.closest('.dk-premium-select');
            var $options = getPremiumSelectList($control).find('.dk-premium-select__option');

            if (event.key === 'Escape') {
                closePremiumSelects();
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();

                if (!$control.hasClass('is-open')) {
                    closePremiumSelects($control);
                    $control.addClass('is-open');
                    $button.attr('aria-expanded', 'true');
                    positionPremiumSelectList($control);
                }

                var $selected = $options.filter('.is-selected').first();
                var $target = $selected.length ? $selected : $options.first();

                $target.focus();
            }
        });

        $(document).on('keydown', '.dk-premium-select__option', function (event) {
            var $option = $(this);
            var $control = getPremiumSelectControlFromOption($option);
            var $button = $control.find('.dk-premium-select__button');
            var $options = getPremiumSelectList($control).find('.dk-premium-select__option');
            var currentIndex = $options.index($option);
            var nextIndex = currentIndex;

            if (event.key === 'Escape') {
                closePremiumSelects();
                $button.focus();
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                choosePremiumOption($option, false);
                return;
            }

            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                return;
            }

            event.preventDefault();
            nextIndex = event.key === 'ArrowDown' ? currentIndex + 1 : currentIndex - 1;

            if (nextIndex < 0) {
                nextIndex = $options.length - 1;
            }

            if (nextIndex >= $options.length) {
                nextIndex = 0;
            }

            $options.eq(nextIndex).focus();
        });

        $(document).on('click', function (event) {
            if (!$(event.target).closest('.dk-premium-select, .dk-premium-select__list').length) {
                closePremiumSelects();
            }
        });

        $(document).on('wheel', function (event) {
            if ($(event.target).closest('.dk-premium-select, .dk-premium-select__list').length) {
                return;
            }

            closePremiumSelects();
        });

        $('[data-dashboard-main-scroll], [data-dashboard-sidebar-scroll]').on('scroll', function () {
            closePremiumSelects();
        });

        $(window).on('resize scroll', function () {
            closePremiumSelects();
        });

        /* Back to Top Click */
        $(document).on('click', '.back-to-top', function () {
            var mainPanel = document.querySelector('[data-dashboard-main-scroll]');
            if (mainPanel) {
                mainPanel.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                $('html, body').animate({ scrollTop: 0 }, 800);
            }
        });

        /* Dismiss verified KYC banner after the user has seen it once. */
        function hasDismissedVerifiedKycNotice(key) {
            try {
                return key && window.localStorage && window.localStorage.getItem(key) === '1';
            } catch (error) {
                return false;
            }
        }

        function rememberVerifiedKycNoticeDismissal(key) {
            try {
                if (key && window.localStorage) {
                    window.localStorage.setItem(key, '1');
                }
            } catch (error) {
                // Keep the visual dismiss working even when storage is blocked.
            }
        }

        $('[data-kyc-verified-notice]').each(function () {
            var notice = this;
            var key = notice.getAttribute('data-kyc-dismiss-key');

            if (hasDismissedVerifiedKycNotice(key)) {
                notice.remove();
                return;
            }

            notice.hidden = false;
        });

        $(document).on('click', '[data-kyc-notice-dismiss]', function () {
            var notice = this.closest('[data-kyc-verified-notice]');
            var key = notice ? notice.getAttribute('data-kyc-dismiss-key') : '';

            rememberVerifiedKycNoticeDismissal(key);

            if (notice) {
                notice.remove();
            }
        });

        /* User Dropdown Toggle */
        $('.navbar-area .header-right li .user').on('click', function (e) {
            e.stopPropagation();
            $(this).toggleClass('active');
        });

        /* Close User Dropdown on Outside Click */
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.navbar-area .header-right li .user').length) {
                $('.navbar-area .header-right li .user').removeClass('active');
            }
        });
    });

    /* On Load Events */
    $(window).on('load', function () {
        var $preloader = $('#preloader');
        $preloader.fadeOut(0);
        $('.back-to-top').fadeOut();

        $(document).on('click', '.cancel-preloader a', function (e) {
            e.preventDefault();
            $preloader.fadeOut(2000);
        });
    });

    /* Initialize Bootstrap Tooltip */
    var tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    /* Initialize ClipboardJS */
    if (typeof ClipboardJS !== 'undefined') {
        var clipboard = new ClipboardJS('.copyNow');
        clipboard.on('success', function (e) {
            var button  = e.trigger;
            var tooltip = bootstrap.Tooltip.getInstance(button);
            if (tooltip) {
                button.setAttribute('data-bs-original-title', 'Copied!');
                tooltip.show();
                setTimeout(function () {
                    var originalTitle = tooltip._config.title || '';
                    button.setAttribute('data-bs-original-title', originalTitle);
                }, 2000);
            }
            e.clearSelection();
        });
    }

    /* -----------------------------------------------------------------
     * § Desktop Scroll Manager
     *   Facebook-style: outside scroll → main panel (smooth rAF lerp),
     *   sidebar menu region → sidebar menu only (native).
     *   Uses requestAnimationFrame for jank-free 60fps scroll routing.
     * ----------------------------------------------------------------- */

    var DESKTOP_MQ   = window.matchMedia('(min-width: 992px)');
    var LERP_FACTOR  = 0.16;
    var SNAP_EPSILON = 0.5;

    var _scroll = {
        mainEl:    null,
        sidebarEl: null,
        targetY:   0,
        currentY:  0,
        ticking:   false
    };

    function _cacheElements() {
        var root = document.querySelector('[data-dashboard-scroll-root]');
        if (!root) {
            _scroll.mainEl    = null;
            _scroll.sidebarEl = null;
            return;
        }
        _scroll.mainEl    = root.querySelector('[data-dashboard-main-scroll]');
        _scroll.sidebarEl = root.querySelector('[data-dashboard-sidebar-scroll]');

        if (_scroll.mainEl) {
            _scroll.currentY = _scroll.mainEl.scrollTop;
            _scroll.targetY  = _scroll.currentY;
        }
    }

    function _isDesktop() {
        return DESKTOP_MQ.matches;
    }

    function _hasOverlay(target) {
        return target && target.closest('.modal.show, .offcanvas.show, .dropdown-menu.show, .dk-premium-select__list');
    }

    function _isInsideSidebar(target) {
        return target && target.closest('[data-dashboard-sidebar-scroll]');
    }

    function _isInsideMainPanel(target) {
        return target && target.closest('[data-dashboard-main-scroll]');
    }

    /* Find a scrollable ancestor between target and main panel */
    function _findNestedScroller(target) {
        if (!target) return null;
        var el = target;
        var mainEl = _scroll.mainEl;
        while (el && el !== mainEl && el !== document.body) {
            if (el.scrollHeight > el.clientHeight + 1) {
                var style = window.getComputedStyle(el);
                var ov = style.overflowY;
                if (ov === 'auto' || ov === 'scroll') {
                    return el;
                }
            }
            el = el.parentElement;
        }
        return null;
    }

    /* Smooth interpolation tick via rAF (60fps lerp like Facebook) */
    function _smoothTick() {
        var s = _scroll;
        if (!s.mainEl) {
            s.ticking = false;
            return;
        }

        var diff = s.targetY - s.currentY;

        if (Math.abs(diff) < SNAP_EPSILON) {
            s.currentY = s.targetY;
            s.mainEl.scrollTop = s.targetY;
            s.ticking = false;
            return;
        }

        s.currentY += diff * LERP_FACTOR;
        s.mainEl.scrollTop = Math.round(s.currentY);

        requestAnimationFrame(_smoothTick);
    }

    function _startSmooth() {
        if (!_scroll.ticking) {
            _scroll.ticking = true;
            requestAnimationFrame(_smoothTick);
        }
    }

    function _clampTarget() {
        var el = _scroll.mainEl;
        if (!el) return;
        var max = el.scrollHeight - el.clientHeight;
        if (_scroll.targetY < 0) _scroll.targetY = 0;
        if (_scroll.targetY > max) _scroll.targetY = max;
    }

    /* Main wheel handler */
    function _onWheel(e) {
        if (!_isDesktop()) return;
        if (_hasOverlay(e.target)) return;

        /* Horizontal scroll → ignore */
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

        /* Sidebar menu region → native sidebar scroll */
        if (_isInsideSidebar(e.target)) return;

        /* Inside main panel → check for nested scrollers first */
        if (_isInsideMainPanel(e.target)) {
            var nested = _findNestedScroller(e.target);
            if (nested) {
                var atTop    = nested.scrollTop <= 0 && e.deltaY < 0;
                var atBottom = (nested.scrollTop >= (nested.scrollHeight - nested.clientHeight - 1)) && e.deltaY > 0;
                if (!atTop && !atBottom) return;
            }
            /* Route to smooth main scroll */
            e.preventDefault();
            _scroll.targetY += e.deltaY;
            _clampTarget();
            _startSmooth();
            return;
        }

        /* Outside both panels (header, wallet card, empty areas) → route to main */
        if (!e.target.closest('.dashboard-user-layout')) return;

        e.preventDefault();
        _scroll.targetY += e.deltaY;
        _clampTarget();
        _startSmooth();
    }

    /* Sync state when user scrolls main panel natively (touch, scrollbar drag) */
    function _onMainScroll() {
        if (!_scroll.mainEl) return;
        if (!_scroll.ticking) {
            _scroll.currentY = _scroll.mainEl.scrollTop;
            _scroll.targetY  = _scroll.currentY;
        }
    }

    /* Reset outer document scroll to 0 (prevent drift) */
    function _resetOuter() {
        if (_isDesktop()) {
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        }
    }

    /*
     * The wheel-lerp scroll routing below is a DESKTOP-only enhancement: it
     * intercepts mouse-wheel events and smoothly lerps the main panel's
     * scrollTop. On mobile/tablet (≤991.98px) the layout switches to a native
     * body scroll, and this manager actively harms it — `_resetOuter()` runs on
     * every `resize`, which mobile browsers fire continuously as the URL bar
     * collapses/expands during a scroll, yanking the page back to the top and
     * causing the scroll to stutter/hang. Gate it to desktop widths.
     */
    function _isDesktopScroll() {
        return window.matchMedia('(min-width: 992px)').matches;
    }

    /* Bootstrap the scroll system */
    function _initScrollManager() {
        if (!_isDesktopScroll()) {
            return;
        }

        _cacheElements();

        if (_scroll.mainEl) {
            _scroll.mainEl.addEventListener('scroll', _onMainScroll, { passive: true });
        }

        document.addEventListener('wheel', _onWheel, { passive: false });

        window.addEventListener('resize', function () {
            if (!_isDesktopScroll()) {
                return;
            }
            _cacheElements();
            _resetOuter();
        });

        _resetOuter();
    }

    /* Start on DOM ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _initScrollManager);
    } else {
        _initScrollManager();
    }

    window.addEventListener('load', function () {
        if (_isDesktopScroll()) {
            _resetOuter();
        }
    });

})(jQuery);
