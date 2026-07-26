/* ==========================================================
   Theme Manager — Landing Colours studio
   Segmented theme switch, colour<->hex sync, live preview,
   per-slot reset. jQuery (already loaded on the admin).
   ========================================================== */
(function ($) {
    'use strict';

    function isHex(value) {
        return /^#[0-9A-Fa-f]{6}$/.test(value);
    }

    $(function () {
        var $studio = $('.tm-colors');
        if (!$studio.length) {
            return;
        }

        function updatePreview($swatch, value) {
            if (!isHex(value)) {
                return;
            }
            var slot = $swatch.data('slot');
            var $preview = $swatch.closest('.tm-colors__panel').find('[data-theme-preview]');
            if ($preview.length) {
                $preview[0].style.setProperty('--c-' + slot, value);
            }
        }

        // Segmented theme switch — show the matching panel.
        $studio.on('click', '.tm-colors__seg', function () {
            var target = $(this).data('theme-target');

            $studio.find('.tm-colors__seg')
                .removeClass('is-selected')
                .attr('aria-selected', 'false');
            $(this).addClass('is-selected').attr('aria-selected', 'true');

            $studio.find('.tm-colors__panel').removeClass('is-shown');
            $studio.find('[data-theme-panel="' + target + '"]').addClass('is-shown');
        });

        // Colour picker -> hex input.
        $studio.on('input change', '[data-color-input]', function () {
            var $swatch = $(this).closest('.tm-swatch');
            var value = this.value.toUpperCase();

            $swatch.find('[data-hex-input]').val(value);
            updatePreview($swatch, value);
        });

        // Hex input -> colour picker.
        $studio.on('input', '[data-hex-input]', function () {
            var $swatch = $(this).closest('.tm-swatch');
            var value = this.value.trim();

            if (value && value.charAt(0) !== '#') {
                value = '#' + value;
            }
            value = value.toUpperCase();
            this.value = value;

            if (isHex(value)) {
                $swatch.find('[data-color-input]').val(value);
                updatePreview($swatch, value);
            }
        });

        // Per-slot reset to its enum default.
        $studio.on('click', '.tm-swatch__reset', function () {
            var $swatch = $(this).closest('.tm-swatch');
            var def = ($swatch.data('default') || '').toString().toUpperCase();

            if (!isHex(def)) {
                return;
            }
            $swatch.find('[data-color-input]').val(def);
            $swatch.find('[data-hex-input]').val(def);
            updatePreview($swatch, def);
        });
    });
})(jQuery);
