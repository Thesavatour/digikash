@extends('backend.settings.index')
@section('setting_title', __('Site Settings'))
@section('setting_content')
	@php
		$activeSection = session()->get('section', 'general_settings');
		$visibleSettings = collect($settings)->except('hide_settings');
		$totalEditableFields = $visibleSettings->sum(
			fn (array $section): int => collect($section['elements'] ?? [])
				->filter(fn (array $field): bool => ($field['type'] ?? null) !== 'hidden')
				->count()
		);
		$settingFieldClass = static function (array $field): string {
			return $field['class'] ?? 'col-12';
		};
		$storageStatus = $storageLinkStatus ?? [
			'linked' => false,
			'public_path' => public_path('storage'),
			'target_path' => storage_path('app/public'),
			'status_label' => __('Missing'),
			'status_tone' => 'danger',
			'functions' => ['exec' => true, 'symlink' => true],
			'functions_ok' => true,
		];
		$storageFunctions = $storageStatus['functions'] ?? ['exec' => true, 'symlink' => true];
		$storageFunctionsOk = $storageStatus['functions_ok'] ?? true;
	@endphp
	
	<div class="settings-site-overview">
		<div class="settings-site-overview__main">
            <span class="settings-site-overview__icon">
                <x-icon name="site-cog" height="28" width="28"/>
            </span>
			<div>
				<span class="settings-site-overview__eyebrow">{{ __('Configuration Workspace') }}</span>
				<h4>{{ __('Site Settings') }}</h4>
				<p>{{ __('Manage brand, role colors, security, email, agent, cookie, and maintenance controls from one structured workspace.') }}</p>
			</div>
		</div>
		<div class="settings-site-overview__stats">
			<button type="button"
			        class="settings-storage-health-link settings-storage-health-link--{{ $storageStatus['status_tone'] }}"
			        data-coreui-toggle="modal"
			        data-coreui-target="#storageHealthModal"
			        aria-label="{{ __('Open storage health tools') }}">
                <span class="settings-storage-health-link__icon">
                    <i class="fas {{ $storageStatus['linked'] ? 'fa-check-circle' : 'fa-unlink' }}"></i>
                </span>
				<span>
                    <strong>{{ __('Storage Health') }}</strong>
                    {{ $storageStatus['status_label'] }}
                </span>
			</button>
			<span>
                <strong>{{ count($settingMenus) }}</strong>
                {{ __('Sections') }}
            </span>
			<span>
                <strong>{{ $totalEditableFields }}</strong>
                {{ __('Editable Fields') }}
            </span>
		</div>
	</div>
	
	<div class="settings-site-shell">
		<div class="settings-site-sidebar rounded px-3"
		     id="settings-site-tablist" role="tablist" aria-orientation="vertical">
			<header class="settings-site-sidebar__header fixed-header mb-3">
                <span class="settings-site-sidebar__header-icon">
                    <x-icon name="cil-menu" height="18" width="18"/>
                </span>
				<span>
                    <strong>{{ __('Settings Menu') }}</strong>
                    <small>{{ __('Choose a section to configure') }}</small>
                </span>
			</header>
			<div class="settings-site-sidebar__nav">
				@foreach($settingMenus as $name => $icon)
					@php
						$menu = $settings[$name] ?? [];
					@endphp
					<button class="settings-site-menu text-start mb-2 {{ ($activeSection ?? 'general_settings') === $name ? 'active' : '' }}"
					        id="v-pills-{{ $name }}-tab"
					        data-coreui-toggle="pill" data-coreui-target="#v-pills-{{ $name }}" type="button" role="tab"
					        aria-controls="v-pills-{{ $name }}"
					        aria-selected="{{ ($activeSection ?? 'general_settings') === $name ? 'true' : 'false' }}">
                        <span class="settings-site-menu__icon">
                            <x-icon name="{{ $icon }}" height="20" width="20"/>
                        </span>
						<span class="settings-site-menu__text">
                            <span class="settings-site-menu__title">{{ __($menu['menu_label'] ?? title($name)) }}</span>
                            @isset($menu['menu_description'])
								<span class="settings-site-menu__description">{{ __($menu['menu_description']) }}</span>
							@endisset
                        </span>
					</button>
				@endforeach
			</div>
		</div>
		<div class="settings-site-content tab-content rounded" id="settings-site-tab-content">
			@foreach($settings as $sectionKey => $fields)
				@php
					$formId = 'settings-site-form-' . $sectionKey;
					$elements = collect($fields['elements'] ?? []);
					$visibleElements = $elements->filter(fn (array $field): bool => ($field['type'] ?? null) !== 'hidden');
					$elementMap = $visibleElements->keyBy('key');
					$groupedFieldKeys = collect($fields['groups'] ?? [])->flatMap(fn (array $group): array => $group['fields'] ?? [])->unique();
					$ungroupedElements = $visibleElements->reject(fn (array $field): bool => $groupedFieldKeys->contains($field['key'] ?? null));
				@endphp
				
				<div class="tab-pane fade {{ ($activeSection ?? 'general_settings') === $sectionKey ? 'show active' : '' }}"
				     id="v-pills-{{ $sectionKey }}" role="tabpanel" aria-labelledby="v-pills-{{ $sectionKey }}-tab"
				     tabindex="0">
					<div class="card settings-site-card">
						<div class="card-header settings-site-card__header d-flex justify-content-between align-items-center fixed-header py-3 px-3">
							<div class="settings-site-card__title">
                                <span class="settings-site-card__title-icon">
                                    <x-icon name="{{ $fields['icon'] ?? 'site-cog' }}" height="22" width="22"/>
                                </span>
								<div>
									<h5 class="mb-0">{{ __($fields['title'] ?? title($sectionKey)) }}</h5>
									<div class="settings-site-card__description">
										{{ __($fields['header_description'] ?? $fields['menu_description'] ?? 'Review and update the controls for this section.') }}
									</div>
								</div>
							</div>
							
							<div class="settings-site-card__actions">
								@if (isset($fields['include_partials']))
									@include('backend.settings.site.partials.' . $fields['include_partials'], ['section' => $sectionKey])
								@endif
								<button type="submit" form="{{ $formId }}" class="btn btn-primary settings-site-header-action settings-site-header-save">
									<x-icon name="check" height="18" width="18"/>
									{{ __('Save Changes') }}
								</button>
							</div>
						</div>
						
						<div class="card-body">
							<form method="POST" id="{{ $formId }}" action="{{ route('admin.settings.site.update', $sectionKey) }}"
							      class="settings-site-form"
							      enctype="multipart/form-data">
								@method('PUT')
								@csrf
								
								<div class="settings-site-scrollarea">
									<div id="errorAlert-{{$sectionKey}}" class="alert alert-danger settings-site-alert d-none" role="alert"></div>
									
									@if (isset($fields['info']))
										<div class="alert alert-info settings-site-info border-0 rounded-2">
											<div class="settings-site-info__icon">
												<x-icon name="info" height="18" width="18"/>
											</div>
											<div>
												<div class="settings-site-info__title">
													{{ __('Important') }}
												</div>
												<div class="settings-site-info__description">
													{{ $fields['info'] }}
												</div>
											</div>
										</div>
									@endif
									
									@if(!empty($fields['groups']))
										<div class="settings-group-stack">
											@foreach($fields['groups'] as $group)
												<section class="settings-field-group">
													<div class="settings-field-group__header">
														<div>
															<h6>{{ __($group['title']) }}</h6>
															@isset($group['description'])
																<p>{{ __($group['description']) }}</p>
															@endisset
														</div>
														<span class="settings-field-group__count">
                                                            {{ count($group['fields'] ?? []) }}
                                                        </span>
													</div>
													<div class="row">
														@foreach($group['fields'] as $fieldKey)
															@php
																$field = $elementMap->get($fieldKey);
															@endphp
															@if($field)
																<div class="mb-3 {{ $settingFieldClass($field) }}">
																	@include('backend.settings.site.partials.fields.' . $field['type'], ['field' => $field])
																</div>
															@endif
														@endforeach
													</div>
												</section>
											@endforeach
											
											@if($ungroupedElements->isNotEmpty())
												<section class="settings-field-group">
													<div class="row">
														@foreach($ungroupedElements as $field)
															<div class="mb-3 {{ $settingFieldClass($field) }}">
																@include('backend.settings.site.partials.fields.' . $field['type'], ['field' => $field])
															</div>
														@endforeach
													</div>
												</section>
											@endif
										</div>
									@else
										<div class="settings-group-stack">
											<section class="settings-field-group settings-field-group--plain">
												<div class="row">
													@foreach($visibleElements as $field)
														<div class="mb-3 {{ $settingFieldClass($field) }}">
															@include('backend.settings.site.partials.fields.' . $field['type'], ['field' => $field])
														</div>
													@endforeach
												</div>
											</section>
										</div>
									@endif
								</div>
							
							</form>
						</div>
					</div>
				</div>
			@endforeach
		</div>
	</div>
	
	@include('backend.settings.site.partials._storage_health')
	
	@include('backend.settings.site.partials._maintenance_mode')

@endsection
@push('scripts')
	<script src="{{ asset('backend/js/settings-site.js?v=' . config('app.version')) }}"></script>
	@include('backend.settings.site.partials._script')
@endpush
