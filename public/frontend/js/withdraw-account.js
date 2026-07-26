"use strict";

$(document).ready(function () {
    const form = document.querySelector("[data-withdraw-account-form]");

    if (!form) {
        return;
    }

    const currencySelect = form.querySelector("[data-withdraw-currency]");
    const methodSelect = form.querySelector("#method-select");
    const accountName = form.querySelector("#accountName");
    const credentialFields = form.querySelector("#credential-fields");
    const urlTemplate = form.dataset.fieldsUrlTemplate || "";
    const loadingText = form.dataset.loadingText || "Loading credential fields...";
    const errorText = form.dataset.errorText || "Unable to load credential fields.";
    const noMethodsText = form.dataset.noMethodsText || "No withdrawal methods are available for this currency.";

    if (!currencySelect || !methodSelect || !credentialFields) {
        return;
    }

    const placeholderDefault = methodSelect.dataset.placeholderDefault || "Select a currency first";
    const placeholderReady = methodSelect.dataset.placeholderReady || "Select Withdrawal Method";
    const placeholderEmpty = methodSelect.dataset.placeholderEmpty || "No withdrawal methods for this currency";
    const methodOptions = Array.from(methodSelect.options)
        .filter((option) => option.value)
        .map((option) => ({
            value: option.value,
            currency: option.dataset.currency || "",
            text: option.textContent,
            selected: option.selected,
        }));

    function setCredentialMessage(message, stateClass) {
        credentialFields.innerHTML = [
            '<div class="col-12">',
            '<div class="withdraw-account-field-state ' + stateClass + '">',
            message,
            "</div>",
            "</div>",
        ].join("");
    }

    function fieldsUrl(methodId) {
        return urlTemplate.replace("__METHOD_ID__", encodeURIComponent(methodId));
    }

    function refreshMethodSelect() {
        if ($.fn.niceSelect && $(methodSelect).next(".nice-select").length) {
            $(methodSelect).niceSelect("update");
        }
    }

    function filterMethodsByCurrency(currency, preserveSelection) {
        const previousValue = preserveSelection ? methodSelect.value : "";
        const placeholder = methodSelect.options[0];
        const matchingOptions = methodOptions.filter((option) => option.currency === currency);

        while (methodSelect.options.length > 1) {
            methodSelect.remove(1);
        }

        const hasCurrency = currency !== "";
        const hasMethods = matchingOptions.length > 0;

        if (placeholder) {
            placeholder.textContent = !hasCurrency
                ? placeholderDefault
                : hasMethods
                    ? placeholderReady
                    : placeholderEmpty;
        }

        matchingOptions.forEach((methodOption) => {
            const option = document.createElement("option");
            option.value = methodOption.value;
            option.dataset.currency = methodOption.currency;
            option.textContent = methodOption.text;
            option.selected = previousValue === methodOption.value || (!previousValue && methodOption.selected);
            methodSelect.appendChild(option);
        });

        methodSelect.disabled = !hasCurrency || !hasMethods;

        if (!methodSelect.value) {
            credentialFields.innerHTML = "";
        }

        if (hasCurrency && !hasMethods) {
            setCredentialMessage(noMethodsText, "is-empty");
        }

        refreshMethodSelect();
    }

    $(currencySelect).on("change", function () {
        filterMethodsByCurrency(this.value || "", false);
    });

    filterMethodsByCurrency(currencySelect.value || "", true);

    $(methodSelect).on("change", function () {
        const methodId = this.value;

        if (!methodId || !urlTemplate) {
            credentialFields.innerHTML = "";
            return;
        }

        setCredentialMessage(loadingText, "is-loading");

        $.ajax({
            url: fieldsUrl(methodId),
            type: "GET",
            success: function (data) {
                credentialFields.innerHTML = data.html || "";

                if (accountName && !accountName.value && data.method_name) {
                    accountName.value = data.method_name;
                }
            },
            error: function () {
                setCredentialMessage(errorText, "is-error");
            },
        });
    });
});
