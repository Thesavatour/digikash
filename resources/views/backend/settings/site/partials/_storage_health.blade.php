<div class="modal fade settings-storage-modal"
     id="storageHealthModal"
     tabindex="-1"
     aria-labelledby="storageHealthModalLabel"
     aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header settings-storage-modal__header settings-storage-modal__header--{{ $storageStatus['status_tone'] }}">
				<div class="settings-storage-modal__title">
                        <span class="settings-storage-modal__icon">
                            <i class="fas {{ $storageStatus['linked'] ? 'fa-check-circle' : 'fa-link' }}"></i>
                        </span>
					<div>
						<span class="settings-storage-modal__eyebrow">{{ __('Storage Health') }}</span>
						<h5 class="modal-title" id="storageHealthModalLabel">{{ __('Public Storage Link') }}</h5>
					</div>
				</div>
				<span class="settings-storage-modal__status-badge settings-storage-modal__status-badge--{{ $storageStatus['status_tone'] }}">
                        <i class="fas {{ $storageStatus['linked'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
                        {{ $storageStatus['status_label'] }}
                    </span>
				<button type="button"
				        class="settings-maintenance-modal__close"
				        data-coreui-dismiss="modal"
				        aria-label="{{ __('Close') }}">
					<x-icon name="close" height="18" width="18"/>
				</button>
			</div>
			<div class="modal-body settings-storage-modal__body">
				<section class="settings-storage-link-panel settings-storage-link-panel--{{ $storageStatus['status_tone'] }}" aria-labelledby="settings-storage-link-title">
					<div class="settings-storage-link-panel__main">
                            <span class="settings-storage-link-panel__icon" aria-hidden="true">
                                <i class="fas fa-link"></i>
                            </span>
						<div class="settings-storage-link-panel__content">
							<h5 id="settings-storage-link-title">{{ __('Regenerate Public Storage Link') }}</h5>
							<p>
								{{ __('Uploaded logos, user files, previews, and public documents need this link before they can load from the browser. Regenerate it any time after server moves or broken uploads. An existing link is unlinked first, then recreated.') }}
							</p>
							<div class="settings-storage-link-panel__paths">
                                    <span>
                                        <strong>{{ __('Command') }}</strong>
                                        <code>php artisan storage:link --force</code>
                                    </span>
								<span>
                                        <strong>{{ __('Public Link') }}</strong>
                                        <code>{{ $storageStatus['public_path'] }}</code>
                                    </span>
								<span>
                                        <strong>{{ __('Target') }}</strong>
                                        <code>{{ $storageStatus['target_path'] }}</code>
                                    </span>
							</div>
						</div>
					</div>
				</section>
				
				<section class="settings-storage-requirement settings-storage-requirement--{{ $storageFunctionsOk ? 'ok' : 'danger' }}" aria-label="{{ __('PHP function requirements') }}">
                        <span class="settings-storage-requirement__icon" aria-hidden="true">
                            <i class="fas {{ $storageFunctionsOk ? 'fa-shield-alt' : 'fa-triangle-exclamation' }}"></i>
                        </span>
					<div class="settings-storage-requirement__content">
						<strong>{{ __('PHP functions required for storage linking') }}</strong>
						<p>{{ __('Creating or updating the storage link needs these PHP functions enabled (not listed in disable_functions). On shared hosting, ask your host or edit php.ini, then restart PHP.') }}</p>
						<div class="settings-storage-requirement__chips">
                                <span class="settings-storage-requirement__chip settings-storage-requirement__chip--{{ ($storageFunctions['symlink'] ?? false) ? 'ok' : 'danger' }}">
                                    <i class="fas {{ ($storageFunctions['symlink'] ?? false) ? 'fa-check' : 'fa-xmark' }}"></i>
                                    <code>symlink()</code>
                                </span>
							<span class="settings-storage-requirement__chip settings-storage-requirement__chip--{{ ($storageFunctions['exec'] ?? false) ? 'ok' : 'danger' }}">
                                    <i class="fas {{ ($storageFunctions['exec'] ?? false) ? 'fa-check' : 'fa-xmark' }}"></i>
                                    <code>exec()</code>
                                </span>
						</div>
					</div>
				</section>
				
				<form method="POST"
				      action="{{ route('admin.settings.site.storage-link') }}"
				      class="settings-storage-modal__form"
				      data-storage-link-form>
					@csrf
					<div class="settings-storage-modal__progress" data-storage-link-progress aria-live="polite">
						<div class="settings-storage-modal__progress-header">
							<span>{{ __('Regenerating storage link') }}</span>
							<strong>{{ __('Working') }}</strong>
						</div>
						<div class="progress settings-storage-modal__progressbar" role="progressbar" aria-label="{{ __('Storage link progress') }}" aria-valuemin="0" aria-valuemax="100">
							<div class="progress-bar progress-bar-striped progress-bar-animated settings-storage-modal__progressbar-fill"></div>
						</div>
						<p>{{ __('Please wait while the current storage link is refreshed and connected again.') }}</p>
					</div>
					
					<div class="settings-storage-modal__actions">
						<button type="button" class="btn btn-light" data-coreui-dismiss="modal">
							<x-icon name="close-1" height="18" width="18"/>
							{{ __('Cancel') }}
						</button>
						<button type="submit"
						        class="btn btn-primary settings-site-header-action"
						        data-storage-link-button
						        data-loading-label="{{ __('Regenerating...') }}">
							<i class="fas fa-terminal"></i>
							<span data-storage-link-button-label>{{ __('Regenerate Storage Link') }}</span>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>