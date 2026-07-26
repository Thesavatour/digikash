"use strict";

/**
 * One-click copy for command blocks on the Scheduler & Queue Guide.
 *
 * Any `[data-copy]` button copies the text of the `[data-copy-source]`
 * element inside its closest `.bt-cmd` wrapper. Localised feedback strings
 * are read from the nearest ancestor carrying `data-copied` /
 * `data-copy-failed`. No build step, no jQuery — vanilla, event-delegated.
 */
(function () {
    "use strict";

    function copyText(text) {
        if (!text) {
            return Promise.reject(new Error("empty"));
        }
        if (window.navigator && window.navigator.clipboard && window.navigator.clipboard.writeText) {
            return window.navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            try {
                var area = document.createElement("textarea");
                area.value = text;
                area.setAttribute("readonly", "");
                area.style.position = "absolute";
                area.style.left = "-9999px";
                document.body.appendChild(area);
                area.select();
                var ok = document.execCommand("copy");
                document.body.removeChild(area);
                ok ? resolve() : reject(new Error("execCommand-failed"));
            } catch (err) {
                reject(err);
            }
        });
    }

    function notify(type, message) {
        if (typeof window.Notify !== "function" || !message) {
            return;
        }
        try {
            new window.Notify({
                status: type,
                text: message,
                effect: "fade",
                speed: 300,
                showIcon: true,
                showCloseButton: false,
                autoclose: true,
                autotimeout: 2200,
                position: "right top",
            });
        } catch (e) {
            /* simple-notify unavailable — ignore */
        }
    }

    function flashCopied(btn) {
        if (!btn) {
            return;
        }
        btn.classList.add("is-copied");
        var icon = btn.querySelector("i");
        var prev = icon ? icon.className : null;
        if (icon) {
            icon.className = "fa-solid fa-check";
        }
        window.setTimeout(function () {
            btn.classList.remove("is-copied");
            if (icon && prev !== null) {
                icon.className = prev;
            }
        }, 1500);
    }

    function messages(el) {
        var root = el.closest("[data-copied]");
        return {
            ok: (root && root.getAttribute("data-copied")) || "Copied to clipboard",
            fail: (root && root.getAttribute("data-copy-failed")) || "Could not copy — select the text manually.",
        };
    }

    document.addEventListener("click", function (event) {
        var btn = event.target.closest("[data-copy]");
        if (!btn) {
            return;
        }
        event.preventDefault();

        var wrap = btn.closest(".bt-cmd");
        var source = wrap ? wrap.querySelector("[data-copy-source]") : null;
        var text = source ? source.textContent.trim() : "";
        var msg = messages(btn);

        copyText(text).then(function () {
            flashCopied(btn);
            notify("success", msg.ok);
        }).catch(function () {
            notify("error", msg.fail);
        });
    });
})();
