@extends('backend.layouts.app')
@section('title', __('Languages Translation'))

@push('styles')
	<link rel="stylesheet" href="{{ asset('backend/css/language-manager.css') }}">
@endpush

@section('content')
	@php
		$sourceLocale = config('app.locale', 'en');
		$totalRows = $translationStats['total'] ?? 0;
		$missingRows = $translationStats['missing'] ?? 0;
		$translatedRows = $translationStats['translated'] ?? 0;
		$hasRows = $translationRows->count() > 0;
	@endphp

	<div class="py-4">
		<div class="d-flex justify-content-between align-items-start w-100 flex-wrap gap-3">
			<div>
				<h1 class="h4 mb-1 d-inline-flex align-items-center gap-2">
					{{ __('Languages Translation') }}
					<span class="badge rounded-pill bg-primary text-uppercase">{{ $languageCode }}</span>
				</h1>
				<p class="text-body-secondary mb-0">{{ __('Translate each key from the source language into the selected language.') }}</p>
			</div>
			<div class="d-flex flex-wrap gap-2">
				<a href="{{ route('admin.language.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center">
					<x-icon name="back" height="20" width="20" class="me-1"/>
					{{ __('Back') }}
				</a>
			</div>
		</div>
	</div>

	<div class="card border-0 shadow-sm mb-4">
		<div class="card-header bg-transparent py-3">
			<form action="{{ route('admin.language.translate', $languageCode) }}" method="get">
				<div class="row g-3 align-items-end">
					<div class="col-12 col-xl-4">
						<label class="form-label small text-body-secondary mb-1" for="filter">{{ __('Search keys & values') }}</label>
						<div class="input-group">
							<span class="input-group-text"><x-icon name="search-2" height="18" width="18"/></span>
							<input type="text" id="filter" class="form-control" name="filter" value="{{ $translateFilters['filter'] }}" placeholder="{{ __('Type to search...') }}">
							@if($translateFilters['filter'])
								<a href="{{ route('admin.language.translate', $languageCode) }}" class="btn btn-outline-secondary" title="{{ __('Clear search') }}">{{ __('Clear') }}</a>
							@endif
							<button class="btn btn-primary" type="submit">{{ __('Search') }}</button>
						</div>
					</div>
					<div class="col-6 col-xl-3">
						<label class="form-label small text-body-secondary mb-1" for="language">{{ __('Language') }}</label>
						@include('backend.languages.partial._select', ['name' => 'language', 'items' => $languages, 'selected' => $languageCode, 'submit' => true])
					</div>
					<div class="col-6 col-xl-3">
						<label class="form-label small text-body-secondary mb-1" for="group">{{ __('Group') }}</label>
						@include('backend.languages.partial._select', ['name' => 'group', 'items' => $groups, 'selected' => $translateFilters['group'], 'optional' => true, 'submit' => true])
					</div>
					<div class="col-6 col-xl-2">
						<label class="form-label small text-body-secondary mb-1" for="per_page">{{ __('Rows') }}</label>
						<select id="per_page" name="per_page" class="form-select" onchange="this.form.submit()">
							@foreach([25, 50, 100] as $size)
								<option value="{{ $size }}" @selected((int) $translateFilters['per_page'] === $size)>{{ $size }}</option>
							@endforeach
						</select>
					</div>
				</div>
			</form>
		</div>

		<div class="card-body">
			<div class="d-flex flex-wrap gap-2 mb-3">
				<span class="badge rounded-pill bg-secondary">{{ __('Total') }}: {{ $totalRows }}</span>
				<span class="badge rounded-pill bg-success">{{ __('Translated') }}: {{ $translatedRows }}</span>
				<span class="badge rounded-pill bg-warning text-dark">{{ __('Missing') }}: {{ $missingRows }}</span>
				<span class="badge rounded-pill bg-info text-dark">{{ __('Showing') }}: {{ $translationRows->firstItem() ?? 0 }} - {{ $translationRows->lastItem() ?? 0 }}</span>
			</div>

			@if($hasRows)
				<div class="table-responsive">
					<table class="table translation-table align-middle mb-0">
						<thead>
						<tr class="text-uppercase small text-body-secondary">
							<th>{{ __('Group / Single') }}</th>
							<th>{{ __('Key') }}</th>
							<th>{{ __('Source') }} ({{ strtoupper($sourceLocale) }})</th>
							<th>{{ __('Target') }} ({{ strtoupper($languageCode) }})</th>
							<th class="text-end">{{ __('Action') }}</th>
						</tr>
						</thead>
						<tbody>
						@include('backend.languages.translate_list')
						</tbody>
					</table>
				</div>

				@if($translationRows->hasPages())
					<div class="mt-3">
						{{ $translationRows->onEachSide(1)->links() }}
					</div>
				@endif
			@else
				<x-admin-not-found
					:title="__('No translation keys found')"
					:message="$translateFilters['filter'] ? __('No keys match your search. Try a different term or clear the filter.') : __('There are no keys for this selection yet. Use Sync Missing Translation on the Languages page to generate them.')"
					icon="fa-key"
				/>
			@endif
		</div>
	</div>

	@include('backend.languages.partial._edit_translate_key')

@endsection

@push('scripts')
	<script>
        (function ($) {
            "use strict";
            $(document).on('click', '.editKeyword', function (e) {
                var $this = $(this);
                var settings = {
                    key: $this.data('key'),
                    value: $this.data('value'),
                    group: $this.data('group'),
                    language: $this.data('language')
                };

                $('.key-label').text('Key: ' + settings.key);
                $('.key-key').val(settings.key);
                $('.key-value').val(settings.value);
                $('.key-group').val(settings.group);
                $('.key-language').val(settings.language);
                $('#updateTranslate').modal('show');
            });
        })(jQuery);
	</script>
@endpush
