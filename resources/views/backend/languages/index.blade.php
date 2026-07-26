@extends('backend.layouts.app')
@section('title', __('Languages'))

@push('styles')
	<link rel="stylesheet" href="{{ asset('backend/css/language-manager.css') }}">
@endpush

@section('content')
	@php
		$statusOptions = [
			'all' => __('All Status'),
			'active' => __('Active'),
			'inactive' => __('Inactive'),
			'default' => __('Default'),
		];
		$directionOptions = [
			'ltr' => __('LTR'),
			'rtl' => __('RTL'),
			'all' => __('All'),
		];
		$hasFilters = filled($filters['search'] ?? null)
			|| ($filters['status'] ?? 'all') !== 'all'
			|| ($filters['direction'] ?? 'all') !== 'all';
	@endphp

	<div class="lmg-page py-4">
		<div class="lmg-hero">
			<div class="lmg-hero__content">
				<div class="lmg-hero__icon" aria-hidden="true">
					<i class="fa-solid fa-globe"></i>
				</div>
				<div>
					<h1 class="lmg-hero__title">{{ __('Language Management') }}</h1>
					<p class="lmg-hero__text">{{ __('Manage your site languages and keep translations in sync.') }}</p>
				</div>
			</div>

			@can('language-manage')
				<div class="lmg-hero__actions">
					<a href="{{ route('admin.language.sync-missing-keys') }}"
					   id="syncMissingBtn"
					   data-url="{{ route('admin.language.sync-missing-keys') }}"
					   class="btn lmg-btn lmg-btn--soft">
						<x-icon name="sync" height="20" width="20"/>
						{{ __('Sync Missing Translations') }}
					</a>
					<button type="button" class="btn lmg-btn lmg-btn--primary" data-coreui-toggle="modal" data-coreui-target="#new-lang-modal">
						<x-icon name="add" height="20" width="20"/>
						{{ __('Add Language') }}
					</button>
				</div>
			@endcan
		</div>

		<div class="lmg-stats">
			<div class="lmg-stat">
				<span class="lmg-stat__icon lmg-stat__icon--primary" aria-hidden="true"><i class="fa-solid fa-globe"></i></span>
				<span class="lmg-stat__label">{{ __('Total Languages') }}</span>
				<strong class="lmg-stat__value">{{ $languageStats['total'] }}</strong>
				<span class="lmg-stat__note">{{ __('All languages added') }}</span>
			</div>
			<div class="lmg-stat">
				<span class="lmg-stat__icon lmg-stat__icon--success" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
				<span class="lmg-stat__label">{{ __('Active Languages') }}</span>
				<strong class="lmg-stat__value">{{ $languageStats['active'] }}</strong>
				<span class="lmg-stat__note">{{ __('Currently active') }}</span>
			</div>
			<div class="lmg-stat">
				<span class="lmg-stat__icon lmg-stat__icon--warning" aria-hidden="true"><i class="fa-solid fa-triangle-exclamation"></i></span>
				<span class="lmg-stat__label">{{ __('Missing Keys') }}</span>
				<strong class="lmg-stat__value lmg-stat__value--warning">{{ $languageStats['missing'] }}</strong>
				<span class="lmg-stat__note">{{ __('Require attention') }}</span>
			</div>
			<div class="lmg-stat">
				<span class="lmg-stat__icon lmg-stat__icon--info" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
				<span class="lmg-stat__label">{{ __('Avg. Completion') }}</span>
				<strong class="lmg-stat__value">{{ $languageStats['average_completion'] }}%</strong>
				<span class="lmg-progress lmg-progress--blue" aria-hidden="true">
					<span style="width: {{ $languageStats['average_completion'] }}%"></span>
				</span>
			</div>
		</div>

		<div class="lmg-filter-panel">
			<form action="{{ route('admin.language.index') }}" method="get" class="lmg-toolbar__form">
				<input type="hidden" name="direction" value="{{ $filters['direction'] }}">
				<input type="hidden" name="per_page" value="{{ $filters['per_page'] }}">

				<div class="lmg-filter-panel__main">
					<label class="lmg-search" for="language-search">
						<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
						<input id="language-search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="{{ __('Search languages...') }}">
					</label>

					<label class="lmg-select" for="language-status">
						<i class="fa-solid fa-filter" aria-hidden="true"></i>
						<select id="language-status" name="status" onchange="this.form.submit()">
							@foreach($statusOptions as $value => $label)
								<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
							@endforeach
						</select>
					</label>

					<button type="submit" class="btn lmg-filter-submit">
						<i class="fa-solid fa-sliders" aria-hidden="true"></i>
						{{ __('Apply') }}
					</button>

					@if($hasFilters)
						<a href="{{ route('admin.language.index') }}" class="btn lmg-reset">
							<i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
							{{ __('Reset') }}
						</a>
					@endif
				</div>

				<div class="lmg-direction" aria-label="{{ __('Filter by direction') }}">
					@foreach($directionOptions as $value => $label)
						<a href="{{ route('admin.language.index', array_merge(request()->except('page'), ['direction' => $value])) }}"
						   class="lmg-direction__item {{ $filters['direction'] === $value ? 'is-active' : '' }}">
							@if($value === 'ltr')
								<i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
							@elseif($value === 'rtl')
								<i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
							@else
								<i class="fa-solid fa-table-cells" aria-hidden="true"></i>
							@endif
							{{ $label }}
						</a>
					@endforeach
				</div>
			</form>
		</div>

		<div class="lmg-board">
			<div class="table-responsive">
				<table class="table align-middle lmg-table lang-table mb-0">
					<thead>
					<tr>
						<th>{{ __('Language') }}</th>
						<th>{{ __('Direction') }}</th>
						<th>{{ __('Status') }}</th>
						<th>{{ __('Progress') }}</th>
						<th>{{ __('Last Updated') }}</th>
						<th class="text-end">{{ __('Actions') }}</th>
					</tr>
					</thead>
					<tbody
						id="language-sortable"
						data-position-url="{{ route('admin.language.position-update') }}"
						data-csrf-token="{{ csrf_token() }}"
						data-order-offset="{{ max(($languages->firstItem() ?? 1) - 1, 0) }}"
						data-success-message="{{ __('Language order updated successfully.') }}"
						data-error-message="{{ __('Unable to update language order right now.') }}"
						data-demo-message="{{ __('This action is disabled in demo mode.') }}"
					>
					@forelse($languages as $language)
						@php
							$metrics = $languageMetrics[$language->code] ?? [
								'translated' => 0,
								'total' => 0,
								'missing' => 0,
								'percentage' => 100,
							];
							$direction = $language->is_rtl ? 'RTL' : 'LTR';
							$statusClass = $language->status ? 'is-active' : 'is-inactive';
							$completionClass = $metrics['percentage'] >= 100 ? 'lmg-progress--green' : 'lmg-progress--blue';
						@endphp
						<tr data-id="{{ $language->id }}">
							<td>
								<div class="lmg-language">
									@can('language-manage')
										<button type="button" class="lmg-drag lmg-drag-handle" aria-label="{{ __('Drag to sort') }}" title="{{ __('Drag to sort') }}">
											<i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
										</button>
									@else
										<span class="lmg-drag" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
									@endcan
									<span class="lang-flag lmg-language__flag">
										<img src="{{ asset($language->flag) }}" alt="{{ $language->name }} {{ __('flag') }}" width="54" height="40" loading="lazy">
									</span>
									<span class="lmg-language__copy">
										<span class="lmg-language__name">
											{{ $language->name }}
											@if($language->is_default)
												<span class="lmg-default">{{ __('Default') }}</span>
											@endif
										</span>
										<span class="lmg-language__code">{{ Str::upper($language->code) }}</span>
									</span>
								</div>
							</td>
							<td>
								<span class="lmg-direction-pill">
									<i class="fa-solid fa-right-left" aria-hidden="true"></i>
									{{ $direction }}
								</span>
							</td>
							<td>
								<span class="lmg-status {{ $statusClass }}">
									<i class="fa-solid fa-circle" aria-hidden="true"></i>
									{{ $language->status ? __('Active') : __('Inactive') }}
								</span>
							</td>
							<td>
								<div class="lmg-progress-cell">
									<span class="lmg-progress-cell__count">{{ $metrics['translated'] }} / {{ $metrics['total'] }}</span>
									<div class="d-flex align-items-center gap-3">
										<span class="lmg-progress {{ $completionClass }}" aria-label="{{ __('Translation completion') }} {{ $metrics['percentage'] }}%">
											<span style="width: {{ $metrics['percentage'] }}%"></span>
										</span>
										<strong>{{ $metrics['percentage'] }}%</strong>
									</div>
								</div>
							</td>
							<td>
								<span class="lmg-updated">
									{{ $language->updated_at?->format('M d, Y') }}
									<small>{{ $language->updated_at?->format('h:i A') }}</small>
								</span>
							</td>
							<td>
								@can('language-manage')
									<div class="lmg-actions">
										<a href="{{ route('admin.language.translate', $language->code) }}" class="btn lmg-action-btn">
											<i class="fa-solid fa-globe" aria-hidden="true"></i>
											{{ __('Translate') }}
										</a>
										<button type="button" data-edit-url="{{ route('admin.language.edit', $language->id) }}" class="edit-modal btn lmg-action-btn">
											<i class="fa-solid fa-gear" aria-hidden="true"></i>
											{{ __('Manage') }}
										</button>
										@if(! $language->is_default && $language->code !== 'en')
											<div class="dropdown">
												<button class="btn lmg-icon-btn" type="button" data-coreui-toggle="dropdown" aria-expanded="false" aria-label="{{ __('More actions') }}">
													<i class="fa-solid fa-ellipsis"></i>
												</button>
												<ul class="dropdown-menu dropdown-menu-end">
													<li>
														<button type="button" data-id="{{ $language->id }}" data-url="{{ route('admin.language.destroy', $language->id) }}" class="delete dropdown-item text-danger">
															<i class="fa-solid fa-trash-can me-2" aria-hidden="true"></i>{{ __('Delete language') }}
														</button>
													</li>
												</ul>
											</div>
										@endif
									</div>
								@else
									<span class="text-body-secondary">-</span>
								@endcan
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="6">
								<x-admin-not-found
									:title="$hasFilters ? __('No languages match these filters') : __('No languages found')"
									:message="$hasFilters ? __('Try a different search, status, or direction filter.') : __('Add a language to start managing translations.')"
									icon="fa-language"
									:action-url="$hasFilters ? route('admin.language.index') : null"
									:action-label="$hasFilters ? __('Reset Filters') : null"
									action-icon="fa-filter-circle-xmark"
								/>
							</td>
						</tr>
					@endforelse
					</tbody>
				</table>
			</div>

			<div class="lmg-footer">
				<form action="{{ route('admin.language.index') }}" method="get" class="lmg-per-page">
					<input type="hidden" name="search" value="{{ $filters['search'] }}">
					<input type="hidden" name="status" value="{{ $filters['status'] }}">
					<input type="hidden" name="direction" value="{{ $filters['direction'] }}">
					<select name="per_page" class="form-select" aria-label="{{ __('Rows per page') }}" onchange="this.form.submit()">
						@foreach([10, 20, 50] as $size)
							<option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }}</option>
						@endforeach
					</select>
				</form>
				<p class="lmg-results">
					{{ __('Showing :first to :last of :total results', [
						'first' => $languages->firstItem() ?? 0,
						'last' => $languages->lastItem() ?? 0,
						'total' => $languages->total(),
					]) }}
				</p>
				@if($languages->hasPages())
					<div class="lmg-pagination">
						{{ $languages->links() }}
					</div>
				@endif
			</div>
		</div>
	</div>

	@can('language-manage')

		{{-- New Lang Modal --}}
		@include('backend.languages.partial._new_lang_modal')

		{{-- Edit Lang Modal --}}
		@include('backend.languages.partial._edit_lang_modal')

		{{-- Sync Missing Translations progress modal --}}
		@include('backend.languages.partial._sync_modal')

	@endcan
@endsection

@push('scripts')
	@can('language-manage')
		<script>
            'use strict';
            $(document).ready(function () {
                editFormByModal('edit-lang-modal', 'edit-lang-append');

                var sortableTarget = document.getElementById('language-sortable');

                if (sortableTarget && typeof Sortable !== 'undefined' && sortableTarget.querySelector('tr[data-id]')) {
                    new Sortable(sortableTarget, {
                        animation: 150,
                        handle: '.lmg-drag-handle',
                        ghostClass: 'sortable-ghost',
                        chosenClass: 'sortable-chosen',
                        onEnd: function () {
                            var endpoint = sortableTarget.dataset.positionUrl;
                            var csrfToken = sortableTarget.dataset.csrfToken;
                            var offset = parseInt(sortableTarget.dataset.orderOffset, 10) || 0;
                            var positions = [];

                            $('#language-sortable tr[data-id]').each(function (index) {
                                positions.push({
                                    id: $(this).data('id'),
                                    order: offset + index + 1
                                });
                            });

                            if (!endpoint || !csrfToken || !positions.length) {
                                return;
                            }

                            $.ajax({
                                url: endpoint,
                                method: 'POST',
                                dataType: 'json',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                data: {
                                    _token: csrfToken,
                                    positions: positions
                                },
                                success: function (response) {
                                    if (response && response.status === 'success') {
                                        notifyEvs('success', response.message || sortableTarget.dataset.successMessage);

                                        return;
                                    }

                                    notifyEvs(response.type || 'warning', (response && response.message) || sortableTarget.dataset.demoMessage);
                                },
                                error: function (xhr) {
                                    var response = xhr.responseJSON || {};
                                    var type = response.type || (response.status === 'demo' ? 'warning' : 'error');

                                    notifyEvs(type, response.message || sortableTarget.dataset.errorMessage);
                                }
                            });
                        }
                    });
                }
            });

            (function ($) {
                "use strict";

                var $modal = $('#syncTranslationModal');

                if (!$modal.length) {
                    return;
                }

                var $bar     = $modal.find('.sync-progress .progress-bar');
                var $progress = $modal.find('.sync-progress');
                var timers   = [];
                var syncUrl  = null;

                var labels = {
                    added:  @json(__('Added :count missing key(s)')),
                    upToDate: @json(__('Everything is up to date')),
                    across: @json(__('Across :count language(s)')),
                    failed: @json(__('Something went wrong while syncing. Please try again.'))
                };

                function clearTimers() {
                    timers.forEach(function (t) { clearTimeout(t); });
                    timers = [];
                }

                function setProgress(pct) {
                    $bar.css('width', pct + '%');
                    $progress.attr('aria-valuenow', pct);
                }

                function showState(name) {
                    $modal.find('[data-sync-state]').attr('hidden', true);
                    $modal.find('[data-sync-state="' + name + '"]').removeAttr('hidden');
                }

                function resetState() {
                    clearTimers();
                    showState('running');
                    $modal.find('[data-sync-action]').attr('hidden', true);
                    $modal.find('[data-sync-dismiss]').attr('hidden', true);
                    $modal.find('.sync-steps li').removeClass('is-active is-done');
                    setProgress(0);
                }

                function runStagedProgress() {
                    var plan = [
                        { pct: 18, step: 'scan' },
                        { pct: 56, step: 'compare' },
                        { pct: 85, step: 'write' }
                    ];
                    var delay = 0;

                    plan.forEach(function (stage, index) {
                        delay += 450 + (index * 350);
                        timers.push(setTimeout(function () {
                            setProgress(stage.pct);
                            $modal.find('.sync-steps li').slice(0, index).addClass('is-done').removeClass('is-active');
                            $modal.find('.sync-steps li[data-step="' + stage.step + '"]').addClass('is-active').removeClass('is-done');
                        }, delay));
                    });
                }

                function renderSuccess(data) {
                    clearTimers();
                    setProgress(100);
                    $modal.find('.sync-steps li').addClass('is-done').removeClass('is-active');

                    timers.push(setTimeout(function () {
                        showState('success');

                        var total = parseInt(data.total, 10) || 0;
                        var langs = (data.languages || []).filter(function (l) { return parseInt(l.added, 10) > 0; });

                        $modal.find('[data-sync-total-title]').text(
                            total > 0 ? labels.added.replace(':count', total) : labels.upToDate
                        );

                        var $breakdown = $modal.find('[data-sync-breakdown]').empty();

                        if (total > 0 && langs.length) {
                            $modal.find('[data-sync-summary]').text(labels.across.replace(':count', langs.length));
                            $breakdown.removeAttr('hidden');
                            $modal.find('[data-sync-empty]').attr('hidden', true);

                            langs.forEach(function (l) {
                                $('<li class="list-group-item d-flex justify-content-between align-items-center"></li>')
                                    .append($('<span class="fw-medium"></span>').text(l.name + ' (' + l.code + ')'))
                                    .append($('<span class="badge bg-success rounded-pill"></span>').text('+' + l.added))
                                    .appendTo($breakdown);
                            });
                        } else {
                            $modal.find('[data-sync-summary]').text('');
                            $breakdown.attr('hidden', true);
                            $modal.find('[data-sync-empty]').removeAttr('hidden');
                        }

                        $modal.find('[data-sync-action="done"]').removeAttr('hidden');
                        $modal.find('[data-sync-dismiss]').removeAttr('hidden');
                    }, 450));
                }

                function renderError(message) {
                    clearTimers();
                    showState('error');
                    $modal.find('[data-sync-error]').text(message || labels.failed);
                    $modal.find('[data-sync-action="retry"]').removeAttr('hidden');
                    $modal.find('[data-sync-action="close"]').removeAttr('hidden');
                    $modal.find('[data-sync-dismiss]').removeAttr('hidden');
                }

                function startSync() {
                    resetState();
                    runStagedProgress();

                    $.ajax({
                        url: syncUrl,
                        method: 'GET',
                        dataType: 'json',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).done(function (data) {
                        renderSuccess(data || {});
                    }).fail(function (xhr) {
                        var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : null;
                        renderError(message);
                    });
                }

                $('#syncMissingBtn').on('click', function (e) {
                    e.preventDefault();
                    syncUrl = $(this).data('url') || $(this).attr('href');
                    $modal.modal('show');
                    startSync();
                });

                $modal.on('click', '[data-sync-action="retry"]', startSync);
                $modal.on('click', '[data-sync-action="done"]', function () {
                    window.location.reload();
                });
            })(jQuery);
		</script>
	@endcan
@endpush
