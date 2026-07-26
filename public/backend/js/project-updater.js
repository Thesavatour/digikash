/* ─────────────────────────────────────────────────────────────────────
   project-updater.js — state controller + install pipeline simulator
   Vanilla jQuery, scoped to .project-updater. No Alpine, no Vite.
   ───────────────────────────────────────────────────────────────────── */

(function ($) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    // Scope to the main page-level updater root, not the modal (which also has
    // the .project-updater class for CSS inheritance).
    var $root = $('.project-updater').not('.pu-modal').first();
    if (!$root.length) {
        return;
    }

    var config = (window.projectUpdaterConfig || {});
    var i18n = $.extend({
        installing: 'Installing…',
        verifying: 'Verifying…',
        checking: 'Checking…',
        activate: 'Activate license',
        check: 'Check for updates',
        autoScrollOn: 'Auto-scroll on',
        autoScrollOff: 'Auto-scroll off',
        showLog: 'Show technical log',
        hideLog: 'Hide technical log',
        showHistory: 'Show install history',
        hideHistory: 'Hide install history',
        copied: 'Copied'
    }, config.i18n || {});

    // ── Pipeline definition ────────────────────────────────────────────
    var PIPELINE = [
        { slug: 'download',         label: 'Download package',            ms: 4000, kind: 'bytes' },
        { slug: 'verify',           label: 'Verify checksum',             ms: 1000, kind: 'spinner' },
        { slug: 'extract',          label: 'Extract files',               ms: 6000, kind: 'files',  files: 2495 },
        { slug: 'backup-files',     label: 'Back up current files',       ms: 3000, kind: 'files',  files: 1500 },
        { slug: 'backup-db',        label: 'Back up database + storage',  ms: 5000, kind: 'spinner' },
        { slug: 'maintenance-on',   label: 'Enable maintenance mode',     ms: 800,  kind: 'spinner' },
        { slug: 'copy-new',         label: 'Copy new files',              ms: 5000, kind: 'files',  files: 247 },
        { slug: 'migrate',          label: 'Run database migrations',     ms: 2000, kind: 'spinner' },
        { slug: 'clear-cache',      label: 'Clear application caches',    ms: 1200, kind: 'spinner' },
        { slug: 'maintenance-off',  label: 'Disable maintenance mode',    ms: 800,  kind: 'spinner' },
        { slug: 'complete',         label: 'Complete',                    ms: 0,    kind: 'done' }
    ];

    var SAMPLE_FILES = [
        'app/Http/Controllers/Backend/ProjectUpdaterController.php',
        'app/Services/ProjectUpdaterService.php',
        'app/Models/ProjectUpdate.php',
        'resources/views/backend/app/updater.blade.php',
        'public/backend/css/project-updater.css',
        'public/backend/js/project-updater.js',
        'database/migrations/2026_05_07_010000_create_project_updater_tables.php',
        'config/project_updater.php',
        'routes/admin.php',
        'composer.lock'
    ];

    function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
    function fmtTime(d) {
        return [d.getHours(), d.getMinutes(), d.getSeconds()]
            .map(function (n) { return String(n).padStart(2, '0'); })
            .join(':');
    }
    function pad2(n) { return n < 10 ? '0' + n : '' + n; }
    function fmtMs(ms) {
        if (ms < 1000) return ms + 'ms';
        var s = ms / 1000;
        return (s < 10 ? s.toFixed(1) : Math.round(s)) + 's';
    }
    function fmtElapsed(s) {
        if (s < 60) return s + 's';
        return Math.floor(s / 60) + 'm ' + pad2(s % 60) + 's';
    }

    function renderPipeline() {
        var $wrap = $('#pu-pipeline');
        if (!$wrap.length) return;
        $wrap.empty();
        PIPELINE.forEach(function (p, i) {
            var $row = $(
                '<div class="pu-pipeline__step pu-pipeline__step--pending" data-slug="' + p.slug + '">' +
                  '<div class="pu-pipeline__indicator"></div>' +
                  '<div class="pu-pipeline__label">' +
                    '<span class="pu-pipeline__num">' + String(i + 1).padStart(2, '0') + '</span>' +
                    p.label +
                  '</div>' +
                  '<div class="pu-pipeline__duration"></div>' +
                '</div>'
            );
            $wrap.append($row);
        });
    }

    var sim = {
        timer: null,
        tickTimer: null,
        actionTimer: null,
        startedAt: null,
        phaseStartedAt: null,
        phaseIndex: 0,
        backupEnabled: true,
        completed: false,
        autoScroll: true,
        phaseDurations: {},
        onSettle: null,

        reset: function () {
            this.stopTimers();
            this.startedAt = null;
            this.phaseStartedAt = null;
            this.phaseIndex = 0;
            this.completed = false;
            this.phaseDurations = {};
            $('#pu-log-body').empty();
            renderPipeline();
            $('#pu-overall-bar').css('width', '0%').removeClass('pu-progress--success pu-progress--danger');
            $('#pu-elapsed').text('0s');
            $('#pu-remaining').text('estimating…');
            $('#pu-overall-pct').text('0%');
            $('#pu-detail-phase').text('Phase 1 of ' + PIPELINE.length);
            $('#pu-detail-title').text('Preparing…');
            $('#pu-detail-action').html('<strong>Initialising update.</strong>');
            $('#pu-stat-1-label').text('Phase elapsed');
            $('#pu-stat-1-value').text('0.0s');
            $('#pu-stat-1-sub').text('');
            $('#pu-stat-2-label').text('Progress');
            $('#pu-stat-2-value').text('—');
            $('#pu-stat-2-sub').text('');
            $('#pu-stat-3-label').text('Step');
            $('#pu-stat-3-value').text('1 / ' + PIPELINE.length);
        },

        stopTimers: function () {
            if (this.timer) { clearTimeout(this.timer); this.timer = null; }
            if (this.tickTimer) { clearInterval(this.tickTimer); this.tickTimer = null; }
            if (this.actionTimer) { clearInterval(this.actionTimer); this.actionTimer = null; }
        },

        log: function (kind, msg) {
            var $line = $(
                '<div class="pu-log__line pu-log__line--' + kind + '">' +
                  '<span class="pu-log__time">[' + fmtTime(new Date()) + ']</span>' +
                  '<span class="pu-log__msg"></span>' +
                '</div>'
            );
            $line.find('.pu-log__msg').text(msg);
            var $body = $('#pu-log-body');
            $body.append($line);
            if (this.autoScroll && $body.length) $body.scrollTop($body[0].scrollHeight);
            var $lines = $body.children();
            if ($lines.length > 200) $lines.slice(0, $lines.length - 200).remove();
        },

        start: function (opts) {
            opts = opts || {};
            this.reset();
            this.backupEnabled = opts.backupEnabled !== false;
            this.onSettle = opts.onSettle;
            this.startedAt = Date.now();
            this.phaseIndex = 0;

            this.log('muted', '── Digikash updater · install run started ──');
            this.log('info', 'Channel: ' + (config.channel || 'stable'));
            this.log('info', 'Target: ' + (config.latestVersion || 'latest') + ' · Current: ' + (config.currentVersion || ''));
            this.log(this.backupEnabled ? 'info' : 'warn', this.backupEnabled
                ? 'Backup mode: database + storage'
                : 'Backup mode: disabled by operator');

            var self = this;
            this.tickTimer = setInterval(function () { self.tick(); }, 250);
            this.advancePhase();
        },

        advancePhase: function () {
            var self = this;
            if (this.phaseIndex >= PIPELINE.length) return;

            var phase = PIPELINE[this.phaseIndex];

            if (phase.slug === 'backup-db' && !this.backupEnabled) {
                this.setStepStatus(phase.slug, 'skipped');
                this.log('muted', 'Skipped: ' + phase.label + ' (operator opted out)');
                this.phaseIndex++;
                return this.advancePhase();
            }

            this.phaseStartedAt = Date.now();
            this.setStepStatus(phase.slug, 'in_progress');

            $('#pu-detail-phase').text('Phase ' + (this.phaseIndex + 1) + ' of ' + PIPELINE.length);
            $('#pu-detail-title').text(phase.label);
            $('#pu-stat-3-value').text((this.phaseIndex + 1) + ' / ' + PIPELINE.length);
            $('#pu-stat-3-sub').text(phase.slug);

            this.log('info', '▸ ' + phase.label + '…');

            this.runPhase(phase);

            if (phase.slug === 'complete') {
                // We reached the last phase but the server hasn't responded yet.
                // Show a "waiting" message instead of a misleading "Complete · 100%".
                $('#pu-detail-phase').text('Finalising');
                $('#pu-detail-title').text('Waiting for server confirmation');
                $('#pu-detail-action').html('<strong></strong>').find('strong').text(
                    'Server is finalising — this should not take more than a few seconds.'
                );
                return;
            }

            this.timer = setTimeout(function () {
                self.phaseDurations[phase.slug] = Date.now() - self.phaseStartedAt;
                self.setStepStatus(phase.slug, 'completed', self.phaseDurations[phase.slug]);
                self.log('success', '✓ ' + phase.label + ' (' + fmtMs(self.phaseDurations[phase.slug]) + ')');
                self.phaseIndex++;
                self.advancePhase();
            }, phase.ms);
        },

        runPhase: function (phase) {
            if (this.actionTimer) { clearInterval(this.actionTimer); this.actionTimer = null; }

            var startMs = Date.now();
            var detailMap = {
                'verify':          'Computing SHA-256 over update package',
                'maintenance-on':  'Calling artisan down',
                'maintenance-off': 'Calling artisan up',
                'clear-cache':     'Clearing config, route, view, event caches',
                'backup-db':       'Dumping database and zipping storage folder',
                'migrate':         'Running pending migrations'
            };

            if (phase.kind === 'bytes') {
                $('#pu-stat-2-label').text('Downloaded');
                $('#pu-stat-2-value').text('0%');
                $('#pu-stat-2-sub').text('—');
                this.actionTimer = setInterval(function () {
                    var pct = Math.min(1, (Date.now() - startMs) / phase.ms);
                    $('#pu-stat-2-value').text(Math.round(pct * 100) + '%');
                    $('#pu-detail-action').html('Downloading <strong>update package</strong>');
                }, 200);
            } else if (phase.kind === 'files') {
                $('#pu-stat-2-label').text('Files');
                $('#pu-stat-2-value').text('0 / ' + phase.files);
                this.actionTimer = setInterval(function () {
                    var pct = Math.min(1, (Date.now() - startMs) / phase.ms);
                    var done = Math.round(phase.files * pct);
                    $('#pu-stat-2-value').text(done.toLocaleString() + ' / ' + phase.files.toLocaleString());
                    $('#pu-stat-2-sub').text(Math.round(pct * 100) + '%');
                    var verb = phase.slug === 'extract' ? 'Extracting' : (phase.slug === 'copy-new' ? 'Writing' : 'Reading');
                    $('#pu-detail-action').html(verb + ' <strong></strong>').find('strong').text(pick(SAMPLE_FILES));
                }, 250);
            } else {
                $('#pu-stat-2-label').text('Status');
                $('#pu-stat-2-value').text('Working…');
                $('#pu-detail-action').html('<strong></strong>').find('strong').text(detailMap[phase.slug] || 'Running…');
            }
        },

        tick: function () {
            if (!this.startedAt) return;
            var elapsedS = Math.floor((Date.now() - this.startedAt) / 1000);
            $('#pu-elapsed').text(fmtElapsed(elapsedS));

            var totalMs = 0, doneMs = 0;
            for (var i = 0; i < PIPELINE.length; i++) {
                var p = PIPELINE[i];
                if (p.slug === 'backup-db' && !this.backupEnabled) continue;
                if (p.slug === 'complete') continue;
                totalMs += p.ms;
                if (i < this.phaseIndex) doneMs += p.ms;
                else if (i === this.phaseIndex) {
                    doneMs += Math.min(p.ms, Date.now() - (this.phaseStartedAt || Date.now()));
                }
            }
            var pct = totalMs ? Math.min(100, (doneMs / totalMs) * 100) : 0;
            $('#pu-overall-bar').css('width', pct.toFixed(1) + '%');
            $('#pu-overall-pct').text(Math.round(pct) + '%');
            var remainingMs = Math.max(0, totalMs - doneMs);
            $('#pu-remaining').text('~' + fmtElapsed(Math.ceil(remainingMs / 1000)) + ' remaining');

            if (this.phaseStartedAt) {
                $('#pu-stat-1-value').text(((Date.now() - this.phaseStartedAt) / 1000).toFixed(1) + 's');
            }
        },

        setStepStatus: function (slug, status, durationMs) {
            var $step = $('.pu-pipeline__step[data-slug="' + slug + '"]');
            $step.removeClass('pu-pipeline__step--pending pu-pipeline__step--in_progress pu-pipeline__step--completed pu-pipeline__step--failed pu-pipeline__step--skipped');
            $step.addClass('pu-pipeline__step--' + status);
            var icon = '';
            if (status === 'completed')      icon = '<i class="fas fa-check"></i>';
            else if (status === 'failed')    icon = '<i class="fas fa-xmark"></i>';
            else if (status === 'skipped')   icon = '<i class="fas fa-minus"></i>';
            $step.find('.pu-pipeline__indicator').html(icon);
            if (durationMs != null) $step.find('.pu-pipeline__duration').text(fmtMs(durationMs));
            else if (status === 'skipped') $step.find('.pu-pipeline__duration').text('Skipped');
        },

        markSuccess: function () {
            this.stopTimers();
            this.completed = true;
            // Fast-forward: mark every remaining step as completed so the pipeline
            // doesn't visually freeze with "pending" steps when the real install
            // returns sooner than the simulator finishes.
            var self = this;
            PIPELINE.forEach(function (p, i) {
                if (i < self.phaseIndex) return;
                if (p.slug === 'backup-db' && !self.backupEnabled) {
                    self.setStepStatus(p.slug, 'skipped');
                    return;
                }
                self.setStepStatus(p.slug, 'completed', 0);
            });
            $('#pu-overall-bar').css('width', '100%').addClass('pu-progress--success');
            $('#pu-overall-pct').text('100%');
            $('#pu-remaining').text('done');
            $('#pu-detail-phase').text('Phase ' + PIPELINE.length + ' of ' + PIPELINE.length);
            $('#pu-detail-title').text('Update installed');
            $('#pu-detail-action').html('<strong></strong>').find('strong').text('All phases completed successfully.');
            $('#pu-stat-3-value').text(PIPELINE.length + ' / ' + PIPELINE.length);
            this.log('success', '✓ Update installed successfully (' + fmtElapsed(Math.floor((Date.now() - this.startedAt) / 1000)) + ').');
            if (typeof this.onSettle === 'function') this.onSettle(true);
        },

        markFailed: function (reason) {
            this.stopTimers();
            this.completed = true;
            var current = PIPELINE[this.phaseIndex] || PIPELINE[0];
            if (current) {
                this.setStepStatus(current.slug, 'failed', Date.now() - (this.phaseStartedAt || Date.now()));
            }
            // Mark steps after the failing one as skipped so the pipeline doesn't
            // sit on a stale "pending" state.
            var self = this;
            PIPELINE.forEach(function (p, i) {
                if (i <= self.phaseIndex) return;
                self.setStepStatus(p.slug, 'skipped');
            });
            $('#pu-overall-bar').addClass('pu-progress--danger');
            $('#pu-remaining').text('halted');
            $('#pu-detail-title').text('Install failed');
            $('#pu-detail-action').html('<strong></strong>').find('strong').text(reason || 'Install failed unexpectedly.');
            this.log('error', '✗ ' + (reason || 'Install failed.'));
            if (typeof this.onSettle === 'function') this.onSettle(false, reason);
        }
    };

    // ── State controller ───────────────────────────────────────────────
    var state = {
        set: function (next) {
            $root.attr('data-state', next);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    // ── Wire-up ────────────────────────────────────────────────────────
    $(function () {
        renderPipeline();

        // Modal: open / close
        $(document).on('click', '[data-action="open-install-modal"]', function () {
            $('#pu-install-modal').attr('data-open', 'true');
        });
        $(document).on('click', '[data-action="close-install-modal"]', function () {
            $('#pu-install-modal').attr('data-open', 'false');
        });
        $('#pu-install-modal').on('click', function (e) {
            if (e.target === this) $(this).attr('data-open', 'false');
        });
        $(document).on('keyup', function (e) {
            if (e.key === 'Escape') {
                $('#pu-install-modal').attr('data-open', 'false');
                $('#pu-help-modal').attr('data-open', 'false');
            }
        });

        // Collapsibles
        $(document).on('click', '[data-action="toggle-history"]', function () {
            var $col = $('#pu-history-collapse');
            var open = $col.attr('data-open') === 'true';
            $col.attr('data-open', open ? 'false' : 'true');
            $(this).find('.pu-collapse__toggle-label').text(open ? i18n.showHistory : i18n.hideHistory);
        });
        $(document).on('click', '[data-action="toggle-log"]', function () {
            var $col = $('#pu-log-collapse');
            var open = $col.attr('data-open') === 'true';
            $col.attr('data-open', open ? 'false' : 'true');
            $(this).find('.pu-collapse__toggle-label').text(open ? i18n.showLog : i18n.hideLog);
        });

        // Auto-scroll toggle
        $(document).on('click', '[data-action="toggle-autoscroll"]', function () {
            sim.autoScroll = !sim.autoScroll;
            $(this).toggleClass('is-active', sim.autoScroll);
            $(this).find('.label').text(sim.autoScroll ? i18n.autoScrollOn : i18n.autoScrollOff);
        });

        // ── Troubleshooting modal ───────────────────────────────────────
        var openHelpModal = function (category) {
            $('#pu-help-modal').attr('data-open', 'true');

            // Collapse every section except the requested one (or the default,
            // which is the section marked data-open="true" in the markup).
            if (category) {
                $('#pu-help-modal .pu-help-section').attr('data-open', 'false');
                var $target = $('#pu-help-modal .pu-help-section[data-category="' + category + '"]');
                if ($target.length) {
                    $target.attr('data-open', 'true');
                    // Scroll the body to that section so it's the first thing visible.
                    var $body = $('#pu-help-modal .pu-modal__body');
                    if ($body.length && $target.length) {
                        $body.scrollTop(0);
                        setTimeout(function () {
                            var top = $target.position().top - 8;
                            $body.scrollTop(top > 0 ? top : 0);
                        }, 60);
                    }
                }
            }
        };
        $(document).on('click', '[data-action="open-help-modal"]', function () {
            openHelpModal($(this).data('open-category'));
        });
        $(document).on('click', '[data-action="close-help-modal"]', function () {
            $('#pu-help-modal').attr('data-open', 'false');
        });
        $('#pu-help-modal').on('click', function (e) {
            if (e.target === this) $(this).attr('data-open', 'false');
        });

        // Accordion toggle inside the help modal
        $(document).on('click', '[data-action="toggle-help-section"]', function () {
            var $section = $(this).closest('.pu-help-section');
            var open = $section.attr('data-open') === 'true';
            $section.attr('data-open', open ? 'false' : 'true');
        });

        // Copy a code block's contents to the clipboard
        $(document).on('click', '[data-action="copy-code"]', function () {
            var $btn = $(this);
            var $pre = $btn.closest('pre');
            if (!$pre.length) return;
            // Grab the pre's text but strip the button's "Copy" label.
            var $clone = $pre.clone();
            $clone.find('button').remove();
            var text = $clone.text().replace(/\s+$/, '');
            try {
                navigator.clipboard.writeText(text);
            } catch (e) {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e2) { /* noop */ }
                document.body.removeChild(ta);
            }
            var original = $btn.html();
            $btn.addClass('is-copied').html('<i class="fas fa-check"></i> ' + (i18n.copied || 'Copied'));
            setTimeout(function () {
                $btn.removeClass('is-copied').html(original);
            }, 1500);
        });

        // Copy chips
        $(document).on('click', '.pu-copy__btn', function () {
            var $b = $(this);
            var text = $b.closest('.pu-copy').find('.pu-copy__val').text();
            try {
                navigator.clipboard.writeText(text);
                var orig = $b.html();
                $b.html('<i class="fas fa-check"></i>');
                setTimeout(function () { $b.html(orig); }, 1200);
            } catch (e) { /* noop */ }
        });

        // License input: format as 8-4-4-4-12, enable submit when valid.
        // Envato purchase code = 32 hex chars formatted with hyphens (36 total).
        var formatLicense = function (el) {
            var raw = el.value
                .replace(/[^a-zA-Z0-9-]/g, '')
                .replace(/-/g, '')
                .toUpperCase()
                .slice(0, 32);
            var groups = [
                raw.slice(0, 8),
                raw.slice(8, 12),
                raw.slice(12, 16),
                raw.slice(16, 20),
                raw.slice(20, 32)
            ].filter(Boolean);
            el.value = groups.join('-');
            var len = raw.length;
            var valid = /^[0-9A-F]{32}$/.test(raw);
            $('#pu-license-activate').prop('disabled', !valid);
            var $hint = $('#pu-license-hint');
            if ($hint.length) {
                $hint.text(len + ' / 32 ' + (i18n.licenseHintChars || 'characters'));
            }
        };
        $('#pu-license-input').on('input', function () { formatLicense(this); });

        // Re-validate on load — old() may have pre-filled a value after server error
        var $licenseInput = $('#pu-license-input');
        if ($licenseInput.length && $licenseInput.val()) {
            formatLicense($licenseInput[0]);
        }

        // Inline re-activate panel (license card footer)
        var formatReactivate = function (el) {
            var raw = el.value
                .replace(/[^a-zA-Z0-9-]/g, '')
                .replace(/-/g, '')
                .toUpperCase()
                .slice(0, 32);
            var groups = [
                raw.slice(0, 8),
                raw.slice(8, 12),
                raw.slice(12, 16),
                raw.slice(16, 20),
                raw.slice(20, 32)
            ].filter(Boolean);
            el.value = groups.join('-');
            var valid = /^[0-9A-F]{32}$/.test(raw);
            $('#pu-reactivate-submit').prop('disabled', !valid);
            var $hint = $('#pu-reactivate-hint');
            if ($hint.length && raw.length > 0) {
                $hint.text(raw.length + ' / 32 ' + (i18n.licenseHintChars || 'characters'));
            }
        };
        $('#pu-reactivate-input').on('input', function () { formatReactivate(this); });

        $(document).on('click', '[data-action="toggle-reactivate"]', function () {
            var $panel = $('#pu-reactivate-panel');
            var isHidden = $panel.prop('hidden');
            $panel.prop('hidden', !isHidden);
            if (!isHidden) {
                $('#pu-reactivate-input').val('');
                $('#pu-reactivate-submit').prop('disabled', true);
            } else {
                setTimeout(function () { $('#pu-reactivate-input').focus(); }, 50);
            }
        });

        $('#pu-reactivate-form').on('submit', function () {
            var $btn = $('#pu-reactivate-submit');
            if ($btn.prop('disabled')) return false;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (i18n.verifying || 'Verifying…'));
        });

        // Show a spinner on the activate button when the form submits
        $('#pu-activate-form').on('submit', function () {
            var $btn = $('#pu-license-activate');
            if ($btn.prop('disabled')) return false;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (i18n.verifying || 'Verifying…'));
        });

        // Show a spinner on check button when the check form submits
        $(document).on('submit', 'form[action$="updater/check"]', function () {
            var $btn = $(this).find('button[type="submit"]');
            if ($btn.prop('disabled')) return false;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (i18n.checking || 'Checking…'));
        });

        // Show a spinner on refresh-license button when the form submits
        $(document).on('submit', '#pu-refresh-license-form', function () {
            var $btn = $(this).find('button[type="submit"]');
            if ($btn.prop('disabled')) return false;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (i18n.refreshing || 'Refreshing…'));
        });

        // Show a spinner on backup-download button when the form submits
        $(document).on('submit', 'form[action$="updater/backup/download"]', function () {
            var $btn = $(this).find('button[type="submit"]');
            if ($btn.prop('disabled')) return false;
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + (i18n.preparing || 'Preparing…'));
            // Re-enable after a moment so the user can re-try if browser blocks download
            setTimeout(function () {
                $btn.prop('disabled', false).html('<i class="fas fa-download"></i> ' + (i18n.generateBackup || 'Generate recovery backup'));
            }, 8000);
        });

        // Install confirmation → submit real form
        $(document).on('click', '#pu-install-confirm', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var backupEnabled = $('#pu-install-backup').is(':checked');
            $('#pu-install-backup-hidden').val(backupEnabled ? '1' : '0');

            $('#pu-install-modal').attr('data-open', 'false');
            state.set('install-progress');

            sim.start({
                backupEnabled: backupEnabled,
                onSettle: function () { /* server response will finalise state */ }
            });

            $btn.prop('disabled', true);

            var $form = $('#pu-install-form');
            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                timeout: 0,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).done(function (response) {
                sim.markSuccess();
                setTimeout(function () {
                    if (response && response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        window.location.reload();
                    }
                }, 900);
            }).fail(function (xhr) {
                var reason = '';
                try {
                    var json = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                    reason = json.message || '';
                    // Surface Laravel validation errors
                    if (!reason && json.errors) {
                        var firstField = Object.keys(json.errors)[0];
                        if (firstField && json.errors[firstField][0]) {
                            reason = json.errors[firstField][0];
                        }
                    }
                } catch (e) { /* noop */ }
                if (!reason) {
                    if (xhr.status === 419) reason = 'Your session expired. Reload and try again.';
                    else if (xhr.status === 403) reason = 'You do not have permission to install updates.';
                    else if (xhr.status === 0)   reason = 'Network error — the install request did not complete.';
                    else reason = 'Install failed (HTTP ' + xhr.status + ').';
                }
                sim.markFailed(reason);
                // Client-side toast so the user gets immediate feedback
                if (typeof window.notifyEvs === 'function') {
                    try { window.notifyEvs('error', reason); } catch (e) { /* noop */ }
                }
                setTimeout(function () {
                    window.location.reload();
                }, 2400);
            });
        });
    });

    // Expose for debugging
    window.PU = { state: state, sim: sim };
})(window.jQuery);
