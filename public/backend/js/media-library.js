"use strict";

(function ($) {
    function toggleProviderFields($form) {
        const driver = $form.find("[data-media-provider-driver]").val();
        const isS3 = driver === "s3";

        $form.find("[data-s3-fields]").toggleClass("d-none", !isS3);
        $form.find("[data-local-fields]").toggleClass("d-none", isS3);
        $form.find("[data-s3-fields] input").each(function () {
            const $input = $(this);
            const shouldRequire = isS3 && !$input.closest("[data-s3-fields]").find(".form-text").length;

            if ($input.attr("name") === "access_key" || $input.attr("name") === "secret_key") {
                return;
            }

            $input.prop("required", shouldRequire && ["bucket", "region"].indexOf($input.attr("name")) !== -1);
        });
    }

    function showCoreuiModal(selector) {
        const modalElement = document.querySelector(selector);

        if (!modalElement || !window.coreui || !window.coreui.Modal) {
            return;
        }

        window.coreui.Modal.getOrCreateInstance(modalElement).show();
    }

    $(function () {
        $("[data-media-provider-form]").each(function () {
            toggleProviderFields($(this));
        });

        $(document).on("change", "[data-media-provider-driver]", function () {
            toggleProviderFields($(this).closest("[data-media-provider-form]"));
        });

        $(document).on("click", "[data-media-copy]", function () {
            const text = $(this).data("media-copy");

            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
                return;
            }

            const input = $("<input>").val(text).appendTo("body").select();
            document.execCommand("copy");
            input.remove();
        });

        $(document).on("click", ".mla-empty-upload .admin-not-found__action", function (event) {
            event.preventDefault();
            showCoreuiModal("#mediaUploadModal");
        });
    });
})(window.jQuery);
