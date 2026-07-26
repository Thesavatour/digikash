"use strict";

/**
 * Scheduler Guide — flexible Cron Builder.
 *
 * Lets an admin pick a schedule frequency (every minute / every N minutes /
 * every N hours / daily / weekly / monthly / custom) and see, live:
 *   - the generated 5-field cron expression
 *   - the full crontab line (cron + the Laravel schedule:run command)
 *   - a plain-English summary
 *
 * No build step, no Alpine — vanilla JS, scoped to [data-cron-builder].
 */
(function () {
    "use strict";

    function init(root) {
        if (!root || root.dataset.cronBuilderBound === "1") {
            return;
        }
        root.dataset.cronBuilderBound = "1";

        var basePath = root.getAttribute("data-base-path") || "";
        var tpl = {
            everyMinute: root.getAttribute("data-tpl-every-minute") || "Runs every minute",
            everyMinutes: root.getAttribute("data-tpl-every-minutes") || "Runs every :n minutes",
            everyHour: root.getAttribute("data-tpl-every-hour") || "Runs every hour",
            everyHours: root.getAttribute("data-tpl-every-hours") || "Runs every :n hours",
            daily: root.getAttribute("data-tpl-daily") || "Runs daily at :time",
            weekly: root.getAttribute("data-tpl-weekly") || "Runs weekly on :day at :time",
            monthly: root.getAttribute("data-tpl-monthly") || "Runs on day :dom every month at :time",
            custom: root.getAttribute("data-tpl-custom") || "Custom schedule",
        };
        var msgCopied = root.getAttribute("data-msg-copied") || "Copied to clipboard";
        var msgCopyFailed = root.getAttribute("data-msg-copy-failed") || "Could not copy.";
        var errCustom = root.getAttribute("data-err-custom") || "Enter 5 space-separated fields.";

        var segButtons = root.querySelectorAll("[data-cron-type]");
        var fields = root.querySelectorAll("[data-cron-field]");
        var exprEl = root.querySelector("[data-cron-expr]");
        var lineEl = root.querySelector("[data-cron-line]");
        var summaryEl = root.querySelector("[data-cron-summary]");
        var summaryWrap = root.querySelector("[data-cron-summary-wrap]");
        var errorEl = root.querySelector("[data-cron-error]");

        var dayNames = (function () {
            var sel = root.querySelector('[data-cron-input="weekly-day"]');
            var map = {};
            if (sel) {
                Array.prototype.forEach.call(sel.options, function (o) {
                    map[o.value] = o.textContent.trim();
                });
            }
            return map;
        })();

        var activeType = "minute";

        function pad(n) {
            n = String(n);
            return n.length < 2 ? "0" + n : n;
        }

        function clampInt(value, min, max, fallback) {
            var n = parseInt(value, 10);
            if (isNaN(n)) {
                return fallback;
            }
            if (n < min) {
                return min;
            }
            if (n > max) {
                return max;
            }
            return n;
        }

        function readVal(key) {
            var el = root.querySelector('[data-cron-input="' + key + '"]');
            return el ? el.value : "";
        }

        function parseTime(value) {
            var parts = String(value || "00:00").split(":");
            var h = clampInt(parts[0], 0, 23, 0);
            var m = clampInt(parts[1], 0, 59, 0);
            return { h: h, m: m, label: pad(h) + ":" + pad(m) };
        }

        function fill(template, replacements) {
            var out = template;
            Object.keys(replacements).forEach(function (k) {
                out = out.replace(":" + k, replacements[k]);
            });
            return out;
        }

        /**
         * @returns {{cron: (string|null), summary: (string|null), error: (string|null)}}
         */
        function build() {
            switch (activeType) {
                case "minute":
                    return { cron: "* * * * *", summary: tpl.everyMinute, error: null };

                case "minutes": {
                    var n = clampInt(readVal("minutes"), 1, 59, 5);
                    if (n === 1) {
                        return { cron: "* * * * *", summary: tpl.everyMinute, error: null };
                    }
                    return {
                        cron: "*/" + n + " * * * *",
                        summary: fill(tpl.everyMinutes, { n: n }),
                        error: null,
                    };
                }

                case "hours": {
                    var h = clampInt(readVal("hours"), 1, 23, 1);
                    if (h === 1) {
                        return { cron: "0 * * * *", summary: tpl.everyHour, error: null };
                    }
                    return {
                        cron: "0 */" + h + " * * *",
                        summary: fill(tpl.everyHours, { n: h }),
                        error: null,
                    };
                }

                case "daily": {
                    var t = parseTime(readVal("daily-time"));
                    return {
                        cron: t.m + " " + t.h + " * * *",
                        summary: fill(tpl.daily, { time: t.label }),
                        error: null,
                    };
                }

                case "weekly": {
                    var wt = parseTime(readVal("weekly-time"));
                    var dow = clampInt(readVal("weekly-day"), 0, 6, 1);
                    return {
                        cron: wt.m + " " + wt.h + " * * " + dow,
                        summary: fill(tpl.weekly, { day: dayNames[String(dow)] || String(dow), time: wt.label }),
                        error: null,
                    };
                }

                case "monthly": {
                    var mt = parseTime(readVal("monthly-time"));
                    var dom = clampInt(readVal("monthly-dom"), 1, 31, 1);
                    return {
                        cron: mt.m + " " + mt.h + " " + dom + " * *",
                        summary: fill(tpl.monthly, { dom: dom, time: mt.label }),
                        error: null,
                    };
                }

                case "custom": {
                    var raw = String(readVal("custom") || "").trim().replace(/\s+/g, " ");
                    var valid = raw !== "" && raw.split(" ").length === 5;
                    return {
                        cron: valid ? raw : null,
                        summary: valid ? tpl.custom : null,
                        error: valid ? null : errCustom,
                    };
                }

                default:
                    return { cron: "* * * * *", summary: tpl.everyMinute, error: null };
            }
        }

        function fullLine(cron) {
            return cron + " cd " + basePath + " && php artisan schedule:run >> /dev/null 2>&1";
        }

        function render() {
            var result = build();
            var hasError = !result.cron;

            if (errorEl) {
                if (result.error && activeType === "custom") {
                    errorEl.textContent = result.error;
                    errorEl.hidden = false;
                } else {
                    errorEl.textContent = "";
                    errorEl.hidden = true;
                }
            }

            if (summaryWrap) {
                summaryWrap.classList.toggle("is-invalid", hasError);
            }

            if (hasError) {
                if (exprEl) {
                    exprEl.textContent = "—";
                }
                if (summaryEl) {
                    summaryEl.textContent = result.error || "";
                }
                if (lineEl) {
                    lineEl.textContent = result.error || "";
                }
                return;
            }

            if (exprEl) {
                exprEl.textContent = result.cron;
            }
            if (summaryEl) {
                summaryEl.textContent = result.summary;
            }
            if (lineEl) {
                lineEl.textContent = fullLine(result.cron);
            }
        }

        function showField(type) {
            Array.prototype.forEach.call(fields, function (f) {
                f.hidden = f.getAttribute("data-cron-field") !== type;
            });
        }

        function setActive(type) {
            activeType = type;
            Array.prototype.forEach.call(segButtons, function (btn) {
                var on = btn.getAttribute("data-cron-type") === type;
                btn.classList.toggle("is-active", on);
                btn.setAttribute("aria-pressed", on ? "true" : "false");
            });
            showField(type);
            render();
        }

        Array.prototype.forEach.call(segButtons, function (btn) {
            btn.addEventListener("click", function () {
                setActive(btn.getAttribute("data-cron-type"));
            });
        });

        root.addEventListener("input", function (event) {
            if (event.target && event.target.hasAttribute("data-cron-input")) {
                render();
            }
        });

        // ----- Copy buttons -----
        function notify(type, message) {
            if (typeof window.Notify !== "function") {
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

        root.addEventListener("click", function (event) {
            var btn = event.target.closest("[data-cron-copy]");
            if (!btn) {
                return;
            }
            event.preventDefault();
            var which = btn.getAttribute("data-cron-copy");
            var text = which === "line"
                ? (lineEl ? lineEl.textContent : "")
                : (exprEl ? exprEl.textContent : "");

            copyText(text).then(function () {
                flashCopied(btn);
                notify("success", msgCopied);
            }).catch(function () {
                notify("error", msgCopyFailed);
            });
        });

        // Initial paint.
        setActive("minute");
    }

    function boot() {
        var builders = document.querySelectorAll("[data-cron-builder]");
        Array.prototype.forEach.call(builders, init);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
