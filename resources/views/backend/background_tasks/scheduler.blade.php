@extends('backend.background_tasks.layout')

@section('title', __('Scheduler & Queue Guide'))
@section('bt_title', __('Scheduler & Queue Guide'))
@section('bt_icon', 'schedule')
@section('bt_subtitle', __('Two cPanel cron jobs power every background task — the scheduler and the queue worker. Set them up once below.'))

@section('bt_content')

@php
    $queueDriver = config('queue.default');
@endphp

<div class="row g-3"
     data-copied="{{ __('Copied to clipboard') }}"
     data-copy-failed="{{ __('Could not copy — select the text manually.') }}">

    {{-- ─── Prerequisites ─────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="bt-banner bt-banner--info">
            <x-icon name="notification" height="14" width="14" class="flex-shrink-0 mt-1"/>
            <div>
                @lang('Every command below needs two values:')
                <span class="fw-semibold">@lang('your app path')</span> (<code>{{ base_path() }}</code>)
                @lang('and your') <span class="fw-semibold">@lang('PHP CLI path')</span>.
                @lang('In cPanel, find the PHP path under') <em>@lang('Software → Select PHP Version / MultiPHP Manager')</em> —
                @lang('it is usually') <code>/usr/local/bin/php</code>. @lang('Inside a cPanel cron job, the bare command') <code>php</code> @lang('often works too.')
            </div>
        </div>
    </div>

    {{-- ─── Step 1 — Scheduler cron ───────────────────────────────── --}}
    <div class="col-12">
        <div class="bt-card bt-step-card">
            <div class="p-3 border-bottom" style="border-color: var(--bt-border) !important;">
                <div class="fw-bold small d-flex align-items-center gap-2">
                    <span class="bt-step-num">1</span>
                    @lang('Scheduler cron')
                    <span class="bt-pill bt-pill--warning ms-1">@lang('Required')</span>
                </div>
            </div>
            <div class="p-3">
                <div class="bt-meta mb-3">
                    @lang('In cPanel open') <em>@lang('Advanced → Cron Jobs')</em>,
                    @lang('add a new job with') <span class="fw-semibold" style="color: var(--bt-text);">@lang('Common Settings → "Once Per Minute (* * * * *)"')</span>,
                    @lang('then paste this in the Command field:')
                </div>

                <div class="bt-cmd">
                    <div class="bt-cmd__head">
                        <span class="bt-cmd__label">@lang('Command')</span>
                        <button type="button" class="bt-cmd__copy" data-copy aria-label="@lang('Copy command')">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            <span>@lang('Copy')</span>
                        </button>
                    </div>
                    <div class="bt-code-card mb-0">
                        <code data-copy-source>/usr/local/bin/php {{ base_path() }}/artisan schedule:run >> /dev/null 2>&1</code>
                    </div>
                </div>

                <div class="bt-banner bt-banner--info mt-3">
                    <x-icon name="notification" height="13" width="13" class="flex-shrink-0 mt-1"/>
                    <span>@lang('This one entry fires all scheduled commands — each runs at its own frequency, decided in code.')</span>
                </div>

                <div class="bt-meta mt-2" style="font-size: 0.72rem;">
                    @lang('Have SSH access instead?') @lang('Run') <code>crontab -e</code> @lang('and add:')
                    <code>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Step 2 — Queue worker cron ────────────────────────────── --}}
    <div class="col-12">
        <div class="bt-card bt-step-card">
            <div class="p-3 border-bottom" style="border-color: var(--bt-border) !important;">
                <div class="fw-bold small d-flex align-items-center gap-2">
                    <span class="bt-step-num">2</span>
                    @lang('Queue worker cron')
                    <span class="bt-pill bt-pill--warning ms-1">@lang('Required')</span>
                    <span class="bt-pill bt-pill--neutral ms-auto">@lang('Driver:') {{ $queueDriver }}</span>
                </div>
            </div>
            <div class="p-3">
                @if ($queueDriver === 'sync')
                    <div class="bt-banner bt-banner--warning mb-3">
                        <x-icon name="warning" height="14" width="14" class="flex-shrink-0 mt-1"/>
                        <div>
                            <span class="fw-semibold">@lang('Your queue driver is "sync" — jobs run instantly inside each web request, so this worker cron does nothing yet.')</span>
                            @lang('To process jobs in the background, set') <code>QUEUE_CONNECTION=database</code> @lang('in your') <code>.env</code>, @lang('then add the cron below.')
                        </div>
                    </div>
                @endif

                <div class="bt-meta mb-3">
                    @lang('Shared hosting cannot run Supervisor, so schedule the worker to drain the queue and exit every minute. Add a second cPanel cron job, also "Once Per Minute", with this command:')
                </div>

                <div class="bt-cmd">
                    <div class="bt-cmd__head">
                        <span class="bt-cmd__label">@lang('Command')</span>
                        <button type="button" class="bt-cmd__copy" data-copy aria-label="@lang('Copy command')">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            <span>@lang('Copy')</span>
                        </button>
                    </div>
                    <div class="bt-code-card mb-0">
                        <code data-copy-source>/usr/local/bin/php {{ base_path() }}/artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1</code>
                    </div>
                </div>

                <div class="bt-cmd mt-3">
                    <div class="bt-cmd__head">
                        <span class="bt-cmd__label">@lang('Optional — prevent overlap (flock)')</span>
                        <button type="button" class="bt-cmd__copy" data-copy aria-label="@lang('Copy command')">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            <span>@lang('Copy')</span>
                        </button>
                    </div>
                    <div class="bt-code-card mb-0">
                        <code data-copy-source>flock -n /tmp/dk-queue.lock /usr/local/bin/php {{ base_path() }}/artisan queue:work --stop-when-empty --max-time=55 --tries=3</code>
                    </div>
                </div>

                <div class="bt-banner bt-banner--warning mt-3">
                    <x-icon name="warning" height="13" width="13" class="flex-shrink-0 mt-1"/>
                    <span>@lang('Do not run a persistent') <code>queue:work --daemon</code> @lang('on shared hosting — the host kills long-running processes. The --max-time guard keeps each run short and self-restarting, so new code is picked up automatically.')</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="bt-card cron-builder" data-cron-builder
             data-base-path="{{ base_path() }}"
             data-tpl-every-minute="{{ __('Runs every minute') }}"
             data-tpl-every-minutes="{{ __('Runs every :n minutes') }}"
             data-tpl-every-hour="{{ __('Runs every hour') }}"
             data-tpl-every-hours="{{ __('Runs every :n hours') }}"
             data-tpl-daily="{{ __('Runs daily at :time') }}"
             data-tpl-weekly="{{ __('Runs weekly on :day at :time') }}"
             data-tpl-monthly="{{ __('Runs on day :dom every month at :time') }}"
             data-tpl-custom="{{ __('Custom schedule') }}"
             data-msg-copied="{{ __('Copied to clipboard') }}"
             data-msg-copy-failed="{{ __('Could not copy — select the text manually.') }}"
             data-err-custom="{{ __('Enter 5 space-separated fields: minute hour day month weekday.') }}">
            <div class="p-3 border-bottom" style="border-color: var(--bt-border) !important;">
                <div class="fw-bold small d-flex align-items-center gap-1">
                    <x-icon name="clock" height="14" width="14" class="text-primary"/>
                    @lang('Cron Builder')
                    <span class="bt-pill bt-pill--neutral ms-2">@lang('Flexible')</span>
                </div>
            </div>
            <div class="p-3">
                <div class="bt-meta mb-3">
                    @lang('Choose how often the scheduler should run and copy the generated cron entry. In cPanel\'s Cron Jobs form, paste the cron expression into the Minute/Hour/Day/Month/Weekday fields and the command separately. Laravel normally runs every minute and decides each command\'s real frequency in code — pick a wider interval only on hosts that limit how often cron can fire.')
                </div>

                {{-- Frequency type selector --}}
                <div class="cron-builder__seg" role="group" aria-label="@lang('Schedule frequency')">
                    <button type="button" class="cron-builder__seg-btn is-active" data-cron-type="minute">@lang('Every minute')</button>
                    <button type="button" class="cron-builder__seg-btn" data-cron-type="minutes">@lang('Every N minutes')</button>
                    <button type="button" class="cron-builder__seg-btn" data-cron-type="hours">@lang('Every N hours')</button>
                    <button type="button" class="cron-builder__seg-btn" data-cron-type="daily">@lang('Daily')</button>
                    <button type="button" class="cron-builder__seg-btn" data-cron-type="weekly">@lang('Weekly')</button>
                    <button type="button" class="cron-builder__seg-btn" data-cron-type="monthly">@lang('Monthly')</button>
                    <button type="button" class="cron-builder__seg-btn" data-cron-type="custom">@lang('Custom')</button>
                </div>

                {{-- Dynamic inputs (one shown at a time) --}}
                <div class="cron-builder__inputs mt-3">
                    <p class="cron-builder__hint" data-cron-field="minute">
                        @lang('The scheduler fires once per minute — the recommended Laravel setup.')
                    </p>

                    <div class="cron-builder__field" data-cron-field="minutes" hidden>
                        <label class="cron-builder__label">@lang('Run every')</label>
                        <div class="cron-builder__inline">
                            <input type="number" min="1" max="59" value="5" class="form-control form-control-sm cron-builder__num" data-cron-input="minutes" aria-label="@lang('Interval in minutes')">
                            <span class="cron-builder__unit">@lang('minutes')</span>
                        </div>
                    </div>

                    <div class="cron-builder__field" data-cron-field="hours" hidden>
                        <label class="cron-builder__label">@lang('Run every')</label>
                        <div class="cron-builder__inline">
                            <input type="number" min="1" max="23" value="1" class="form-control form-control-sm cron-builder__num" data-cron-input="hours" aria-label="@lang('Interval in hours')">
                            <span class="cron-builder__unit">@lang('hours')</span>
                        </div>
                    </div>

                    <div class="cron-builder__field" data-cron-field="daily" hidden>
                        <label class="cron-builder__label">@lang('Run daily at')</label>
                        <input type="time" value="00:00" class="form-control form-control-sm cron-builder__time" data-cron-input="daily-time" aria-label="@lang('Time of day')">
                    </div>

                    <div class="cron-builder__field" data-cron-field="weekly" hidden>
                        <label class="cron-builder__label">@lang('Run weekly on')</label>
                        <div class="cron-builder__inline cron-builder__inline--wrap">
                            <select class="form-select form-select-sm cron-builder__day" data-cron-input="weekly-day" aria-label="@lang('Day of week')">
                                <option value="1">@lang('Monday')</option>
                                <option value="2">@lang('Tuesday')</option>
                                <option value="3">@lang('Wednesday')</option>
                                <option value="4">@lang('Thursday')</option>
                                <option value="5">@lang('Friday')</option>
                                <option value="6">@lang('Saturday')</option>
                                <option value="0">@lang('Sunday')</option>
                            </select>
                            <span class="cron-builder__unit">@lang('at')</span>
                            <input type="time" value="00:00" class="form-control form-control-sm cron-builder__time" data-cron-input="weekly-time" aria-label="@lang('Time of day')">
                        </div>
                    </div>

                    <div class="cron-builder__field" data-cron-field="monthly" hidden>
                        <label class="cron-builder__label">@lang('Run monthly on day')</label>
                        <div class="cron-builder__inline cron-builder__inline--wrap">
                            <input type="number" min="1" max="31" value="1" class="form-control form-control-sm cron-builder__num" data-cron-input="monthly-dom" aria-label="@lang('Day of month')">
                            <span class="cron-builder__unit">@lang('at')</span>
                            <input type="time" value="00:00" class="form-control form-control-sm cron-builder__time" data-cron-input="monthly-time" aria-label="@lang('Time of day')">
                        </div>
                    </div>

                    <div class="cron-builder__field" data-cron-field="custom" hidden>
                        <label class="cron-builder__label">@lang('Cron expression')</label>
                        <input type="text" value="* * * * *" class="form-control form-control-sm cron-builder__custom" data-cron-input="custom" placeholder="* * * * *" autocomplete="off" spellcheck="false" aria-label="@lang('Custom cron expression')">
                        <small class="cron-builder__error" data-cron-error hidden></small>
                    </div>
                </div>

                {{-- Live output --}}
                <div class="cron-builder__output mt-3">
                    <div class="cron-builder__summary" data-cron-summary-wrap>
                        <span class="cron-builder__summary-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                        <span data-cron-summary>@lang('Runs every minute')</span>
                    </div>

                    <div class="cron-builder__expr">
                        <span class="cron-builder__expr-label">@lang('Cron expression')</span>
                        <code class="cron-builder__expr-value" data-cron-expr>* * * * *</code>
                        <button type="button" class="cron-builder__chip-copy" data-cron-copy="expr" aria-label="@lang('Copy cron expression')">
                            <i class="fa-regular fa-copy" aria-hidden="true"></i>
                            <span>@lang('Copy')</span>
                        </button>
                    </div>

                    <div class="bt-code-card cron-builder__line-card">
                        <code data-cron-line>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                    </div>
                    <button type="button" class="btn btn-sm btn-light-primary d-inline-flex align-items-center gap-2" data-cron-copy="line">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                        @lang('Copy full crontab line')
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="bt-card">
            <div class="p-3 border-bottom" style="border-color: var(--bt-border) !important;">
                <div class="fw-bold small d-flex align-items-center gap-1">
                    <x-icon name="apps" height="14" width="14" class="text-primary"/>
                    @lang('Scheduled Commands')
                </div>
            </div>
            <div class="table-responsive">
                <table class="bt-table bt-schedule-table">
                    <thead>
                        <tr>
                            <th>@lang('Signature')</th>
                            <th>@lang('Frequency')</th>
                            <th>@lang('Description')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code class="small">p2p:orders:expire</code></td>
                            <td><span class="bt-pill bt-pill--info">@lang('Every minute')</span></td>
                            <td class="bt-meta">@lang('Expire pending P2P orders that passed expiry time and refund escrow.')</td>
                        </tr>
                        <tr>
                            <td><code class="small">p2p:promotions:expire</code></td>
                            <td><span class="bt-pill bt-pill--info">@lang('Every minute')</span></td>
                            <td class="bt-meta">@lang('Expire active P2P offer promotions that passed their end time.')</td>
                        </tr>
                        <tr>
                            <td><code class="small">wallet-earn:process</code></td>
                            <td><span class="bt-pill bt-pill--info">@lang('Every minute')</span></td>
                            <td class="bt-meta">@lang('Process due Wallet Earn reward payouts and matured principal returns.')</td>
                        </tr>
                        <tr>
                            <td><code class="small">subscription:process --renewals</code></td>
                            <td><span class="bt-pill bt-pill--neutral">@lang('Hourly')</span></td>
                            <td class="bt-meta">@lang('Charge due subscription renewals and apply plan changes.')</td>
                        </tr>
                        <tr>
                            <td><code class="small">license:heartbeat</code></td>
                            <td><span class="bt-pill bt-pill--neutral">@lang('Twice daily (03:00, 15:00)')</span></td>
                            <td class="bt-meta">@lang('Re-verify the project license against the update server.')</td>
                        </tr>
                        <tr>
                            <td><code class="small">@lang('Temp media cleanup')</code></td>
                            <td><span class="bt-pill bt-pill--neutral">@lang('Daily at 02:00')</span></td>
                            <td class="bt-meta">@lang('Delete yesterday\'s Summernote temporary upload folder.')</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top" style="border-color: var(--bt-border) !important;">
                <div class="bt-meta" style="font-size: 0.72rem;">
                    <x-icon name="clock" height="12" width="12" class="me-1"/>
                    @lang('Times shown are in the application timezone') (<code>{{ config('app.timezone') }}</code>).
                </div>
            </div>
        </div>
    </div>

    {{-- ─── Advanced: VPS / SSH (Supervisor) ──────────────────────── --}}
    <div class="col-12">
        <button class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                type="button"
                data-coreui-toggle="collapse"
                data-coreui-target="#supervisor-block"
                aria-expanded="false">
            <x-icon name="cil-settings" height="13" width="13"/>
            @lang('Advanced — VPS / SSH (Supervisor)')
        </button>
        <div class="collapse mt-2" id="supervisor-block">
            <div class="bt-card">
                <div class="p-3">
                    <div class="bt-banner bt-banner--warning mb-3">
                        <x-icon name="warning" height="13" width="13" class="flex-shrink-0 mt-1"/>
                        <span>@lang('Only for servers with SSH / root access — not needed on cPanel shared hosting. On a VPS, run the queue worker under Supervisor so it stays alive and auto-restarts.')</span>
                    </div>
                    <div class="bt-meta mb-2">
                        @lang('Save as') <code>/etc/supervisor/conf.d/laravel-worker.conf</code>,
                        @lang('then run:') <code>supervisorctl reread && supervisorctl update && supervisorctl start laravel-worker:*</code>
                    </div>
                    <div class="bt-code-card">
                        <code>[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php {{ base_path() }}/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile={{ storage_path('logs/worker.log') }}</code>
                    </div>
                    <div class="bt-meta mt-3">
                        @lang('After deploying new code on a VPS, reload the workers with') <code>php artisan queue:restart</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('backend/js/cron-builder.js?v=' . config('app.version') . '-' . filemtime(public_path('backend/js/cron-builder.js'))) }}"></script>
    <script src="{{ asset('backend/js/command-copy.js?v=' . config('app.version') . '-' . filemtime(public_path('backend/js/command-copy.js'))) }}"></script>
@endpush
