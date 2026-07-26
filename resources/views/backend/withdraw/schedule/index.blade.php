@extends('backend.withdraw.index')

@section('title', __('Withdraw Schedule'))

@section('withdraw_header')
    <x-admin-feature-header
        :title="__('Withdraw Schedule')"
        :subtitle="__('Set and manage the schedule for automatic withdrawals. The system will process withdrawals on the selected days.')"
        icon="calendar"
    />
@endsection

@section('withdraw_content')
    @php($activeDays = $withdrawSchedules->where('status', true)->count())

    <div class="d-grid gap-3">
        {{-- How it works --}}
        <div class="wd-note">
            <span class="wd-note__icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
            <div>
                <strong>{{ __('How automatic withdrawals work') }}</strong>
                <p>{{ __('Turn on the days you want the system to process withdrawals automatically. Example: enable Monday and every Monday the queued withdrawals are released.') }}</p>
            </div>
        </div>

        {{-- Schedule --}}
        <form action="{{ route('admin.withdraw.schedule.update') }}" method="POST">
            @csrf
            <div class="wd-schedule">
                <div class="wd-schedule__bar">
                    <div>
                        <h2 class="wd-schedule__title">{{ __('Weekly Schedule') }}</h2>
                        <span class="wd-schedule__summary">
                            <span data-active-count>{{ $activeDays }}</span> {{ __('of :total days active', ['total' => $withdrawSchedules->count()]) }}
                        </span>
                    </div>
                    <div class="wd-schedule__quick">
                        <button type="button" class="wd-quick" data-schedule-all="1">
                            <i class="fa-solid fa-check-double"></i> {{ __('Enable all') }}
                        </button>
                        <button type="button" class="wd-quick" data-schedule-all="0">
                            <i class="fa-solid fa-xmark"></i> {{ __('Clear all') }}
                        </button>
                    </div>
                </div>

                <div class="wd-schedule__list">
                    @foreach($withdrawSchedules as $key => $withdrawSchedule)
                        <label class="wd-day {{ $withdrawSchedule->status ? 'is-active' : '' }}">
                            <span class="wd-day__icon" aria-hidden="true"><i class="fa-regular fa-calendar-check"></i></span>
                            <span class="wd-day__copy">
                                <span class="wd-day__name">{{ ucfirst($withdrawSchedule->day) }}</span>
                                <span class="wd-day__hint">{{ __('Process withdrawals every :day', ['day' => ucfirst($withdrawSchedule->day)]) }}</span>
                            </span>
                            <span class="wd-day__state" data-day-state>{{ $withdrawSchedule->status ? __('Active') : __('Off') }}</span>
                            <span class="form-check form-switch wd-day__switch">
                                <input class="form-check-input coevs-switch" type="checkbox" role="switch"
                                       name="status[{{ $withdrawSchedule->day }}]" value="Active"
                                       aria-label="{{ ucfirst($withdrawSchedule->day) }}"
                                       @checked($withdrawSchedule->status)>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="wd-schedule__footer">
                    <span class="wd-schedule__hint text-muted small">
                        <i class="fa-regular fa-clock"></i> {{ __('Changes apply to upcoming automatic withdrawals.') }}
                    </span>
                    <button type="submit" class="btn btn-primary fw-semibold">
                        <x-icon name="check" height="20"/> {{ __('Update Schedule') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        'use strict';
        (function () {
            const root = document.querySelector('.wd-schedule');
            if (!root) {
                return;
            }

            const countEl  = root.querySelector('[data-active-count]');
            const switches = Array.from(root.querySelectorAll('.wd-day__switch input[type="checkbox"]'));
            const labels   = { on: @json(__('Active')), off: @json(__('Off')) };

            function refresh() {
                let active = 0;

                switches.forEach(function (sw) {
                    const row   = sw.closest('.wd-day');
                    const state = row ? row.querySelector('[data-day-state]') : null;

                    if (sw.checked) {
                        active++;
                        row && row.classList.add('is-active');
                        if (state) { state.textContent = labels.on; }
                    } else {
                        row && row.classList.remove('is-active');
                        if (state) { state.textContent = labels.off; }
                    }
                });

                if (countEl) { countEl.textContent = active; }
            }

            switches.forEach(function (sw) {
                sw.addEventListener('change', refresh);
            });

            root.querySelectorAll('[data-schedule-all]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const on = this.getAttribute('data-schedule-all') === '1';
                    switches.forEach(function (sw) { sw.checked = on; });
                    refresh();
                });
            });
        })();
    </script>
@endpush
