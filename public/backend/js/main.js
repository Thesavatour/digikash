"use strict";

$(function () {
    // Initialize Tooltips
    $('[data-coreui-toggle="tooltip"]').each(function () {
        new coreui.Tooltip(this);
    });

    // Initialize Popovers
    $('[data-coreui-toggle="popover"]').each(function () {
        new coreui.Popover(this);
    });

    // Header Shadow on Scroll
    const $header = $('header.header');
    $(document).on('scroll', function () {
        $header.toggleClass('shadow-sm', $(document).scrollTop() > 0);
    });

    // Initialize Tagify
    const $tagInput = $('.tags-evs');
    if ($tagInput.length) {
        new Tagify($tagInput[0]);
    }

    // Initialize Summernote
    initializeSummernote('.summernote');

    // Initialize Clipboard.js
    const clipboard = new ClipboardJS('.copyNow');
    clipboard.on('success', function (e) {
        const button = e.trigger;
        const tooltip = coreui.Tooltip.getInstance(button);
        if (tooltip) {
            tooltip.setContent({'.tooltip-inner': 'Copied!'});
            tooltip.show();
        }
    });

    $(document).on('click', '.sidebar-quick-link[href]', function (event) {
        const link = this;
        const href = link.getAttribute('href');

        if (!href || href === '#' || link.target === '_blank' || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const tooltip = coreui.Tooltip.getInstance(link);

        if (tooltip) {
            tooltip.hide();
        }

        window.setTimeout(function () {
            if (event.isDefaultPrevented()) {
                window.location.assign(href);
            }
        }, 0);
    });

    // Auto Slugify Title Inputs
    $(document).on('input', '.title-to-slug', function () {
        const $this = $(this);
        const $target = $($this.data('slug-target'));
        $target.val(slugify($this.val()));
    });

    // Scroll to Active Sidebar Menu Item (Once)
    const $activeSidebarItem = $('.sidebar .nav-link.active');
    if ($activeSidebarItem.length) {
        $activeSidebarItem[0].scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }


});
document.addEventListener("DOMContentLoaded", () => {
    // 1️⃣ Override the internal _setActiveLink method so it does nothing
    if (coreui.Navigation && coreui.Navigation.prototype) {
        coreui.Navigation.prototype._setActiveLink = function() {
            /* no-op */
        };
    }

    // 2️⃣ Now (re)initialize your navigation component as usual
    document.querySelectorAll('[data-coreui="navigation"]')
        .forEach(el => {
            coreui.Navigation.getOrCreateInstance(el, {
                // you can still control collapse behavior…
                groupsAutoCollapse: true,
                // …but activeLinksExact no longer matters, since _setActiveLink is empty
            });
        });

    // 3️⃣ Sidebar Toggle Handler
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('admin-header-sidebar-toggle');
    const menuIcon = toggleButton?.querySelector('.sidebar-toggle-icon--menu');
    const collapseIcon = toggleButton?.querySelector('.sidebar-toggle-icon--collapse');
    const expandIcon = toggleButton?.querySelector('.sidebar-toggle-icon--expand');

    if (sidebar && toggleButton) {
        const updateToggleIcons = function (isCollapsed) {
            const isDesktop = window.innerWidth >= 992;

            if (menuIcon) {
                menuIcon.classList.toggle('d-none', isDesktop);
            }

            if (collapseIcon && expandIcon) {
                if (!isDesktop) {
                    collapseIcon.classList.add('d-none');
                    expandIcon.classList.add('d-none');

                    return;
                }

                if (isCollapsed) {
                    collapseIcon.classList.add('d-none');
                    expandIcon.classList.remove('d-none');
                } else {
                    collapseIcon.classList.remove('d-none');
                    expandIcon.classList.add('d-none');
                }
            }
        };

        const syncToggleState = function () {
            const isDesktop = window.innerWidth >= 992;
            const isCollapsed = isDesktop && (
                sidebar.classList.contains('sidebar-narrow-unfoldable') || sidebar.classList.contains('sidebar-narrow')
            );

            toggleButton.setAttribute('aria-pressed', isCollapsed ? 'true' : 'false');
            toggleButton.classList.toggle('is-active', isCollapsed);
            updateToggleIcons(isCollapsed);
        };

        toggleButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (window.innerWidth >= 992) {
                sidebar.classList.remove('sidebar-narrow');
                sidebar.classList.toggle('sidebar-narrow-unfoldable');
                syncToggleState();

                return;
            }

            openMobileSidebar(sidebar);
        });

        window.addEventListener('resize', syncToggleState);

        syncToggleState();
    }

    const searchToggle = document.getElementById('admin-header-search-toggle');
    const searchPanel = document.getElementById('admin-header-search-panel');
    const searchInput = document.getElementById('admin-header-search');

    if (searchToggle && searchPanel) {
        const closeMobileHeaderSearch = function () {
            searchPanel.classList.remove('is-open');
            searchToggle.setAttribute('aria-expanded', 'false');
        };

        const openMobileHeaderSearch = function () {
            searchPanel.classList.add('is-open');
            searchToggle.setAttribute('aria-expanded', 'true');

            if (searchInput) {
                window.setTimeout(function () {
                    searchInput.focus();
                }, 120);
            }
        };

        searchToggle.addEventListener('click', function (event) {
            event.preventDefault();

            if (searchPanel.classList.contains('is-open')) {
                closeMobileHeaderSearch();

                return;
            }

            openMobileHeaderSearch();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && searchPanel.classList.contains('is-open')) {
                closeMobileHeaderSearch();
                searchToggle.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth >= 576 || !searchPanel.classList.contains('is-open')) {
                return;
            }

            if (searchPanel.contains(event.target) || searchToggle.contains(event.target)) {
                return;
            }

            closeMobileHeaderSearch();
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 576) {
                closeMobileHeaderSearch();
            }
        });
    }

    /*
     * Mobile sidebar open/close helpers.
     *
     * The header toggle (above) opens the sidebar; the in-sidebar
     * close button (`[data-sidebar-close]`) closes it. Both go
     * through `openMobileSidebar` / `closeMobileSidebar` so we have
     * a single place to handle every CoreUI version + degraded
     * environments where the CoreUI JS hasn't loaded yet.
     *
     * Fallback ladder, in order:
     *   1. coreui.Sidebar  — primary CoreUI 5 API
     *   2. coreui.Offcanvas — some CoreUI builds treat mobile
     *      sidebar as an Offcanvas under the hood
     *   3. Manual class toggle — last-resort guarantee that the
     *      close button always works even if all JS bindings fail
     */
    function openMobileSidebar(el) {
        if (!el) {
            return;
        }
        try {
            if (window.coreui && coreui.Sidebar) {
                coreui.Sidebar.getOrCreateInstance(el).show();
                return;
            }
            if (window.coreui && coreui.Offcanvas) {
                coreui.Offcanvas.getOrCreateInstance(el).show();
                return;
            }
        } catch (e) {
            /* fall through to manual */
        }
        el.classList.add('show');
    }

    function closeMobileSidebar(el) {
        if (!el) {
            return;
        }
        try {
            if (window.coreui && coreui.Sidebar) {
                coreui.Sidebar.getOrCreateInstance(el).hide();
                return;
            }
            if (window.coreui && coreui.Offcanvas) {
                coreui.Offcanvas.getOrCreateInstance(el).hide();
                return;
            }
        } catch (e) {
            /* fall through to manual */
        }
        el.classList.remove('show');
    }

    /*
     * Delegated close handler. Lives at document level so it works
     * regardless of when CoreUI initializes or whether the close
     * button is re-rendered by another partial.
     */
    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('[data-sidebar-close]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        const target = document.getElementById(trigger.getAttribute('aria-controls') || 'sidebar');
        closeMobileSidebar(target);
    });
});
