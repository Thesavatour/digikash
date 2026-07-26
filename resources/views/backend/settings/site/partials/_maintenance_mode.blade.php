<div class="modal fade settings-maintenance-modal"
     id="settingsEnableWarningModal"
     tabindex="-1"
     aria-labelledby="settingsEnableWarningModalLabel"
     aria-hidden="true"
     data-settings-enable-warning-modal>
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header settings-maintenance-modal__header">
				<div class="settings-maintenance-modal__title">
                        <span class="settings-maintenance-modal__icon">
                            <x-icon name="warning-2" height="22" width="22"/>
                        </span>
					<div>
						<span class="settings-maintenance-modal__eyebrow">{{ __('Recovery Check') }}</span>
						<h5 class="modal-title" id="settingsEnableWarningModalLabel" data-settings-warning-title>
							{{ __('Enable Maintenance Mode?') }}
						</h5>
					</div>
				</div>
				<button type="button"
				        class="settings-maintenance-modal__close"
				        data-coreui-dismiss="modal"
				        aria-label="{{ __('Close') }}">
					<x-icon name="close" height="18" width="18"/>
				</button>
			</div>
			<div class="modal-body settings-maintenance-modal__body">
				<div class="settings-maintenance-modal__notice">
                        <span class="settings-maintenance-modal__notice-icon">
                            <x-icon name="shield" height="20" width="20"/>
                        </span>
					<p data-settings-warning-message>
						{{ __('Before enabling Maintenance Mode, copy and remember the Secret Key. Without it, the client may not be able to access the site or restore it later.') }}
					</p>
				</div>
				
				<div class="settings-maintenance-modal__secret">
					<div>
						<span class="settings-maintenance-modal__secret-label">{{ __('Secret Key') }}</span>
						<strong data-settings-warning-secret>{{ setting('secret_key', 'secret') }}</strong>
					</div>
					<button type="button"
					        class="settings-maintenance-modal__copy"
					        data-settings-copy-secret
					        data-settings-copy-success="{{ __('Copied') }}">
						<x-icon name="clipboard" height="17" width="17"/>
						<span data-settings-copy-secret-label>{{ __('Copy Key') }}</span>
					</button>
				</div>
				
				<p class="settings-maintenance-modal__hint">
					{{ __('Save the key somewhere safe before turning maintenance mode on.') }}
				</p>
			</div>
			<div class="modal-footer settings-maintenance-modal__footer">
				<button type="button" class="btn btn-light" data-coreui-dismiss="modal">
					<x-icon name="close-1" height="18" width="18"/>
					{{ __('Cancel') }}
				</button>
				<button type="button" class="btn btn-primary" data-settings-enable-warning-confirm>
					<x-icon name="check" height="18" width="18"/>
					<span data-settings-enable-warning-confirm-label>{{ __('Yes, Enable Maintenance') }}</span>
				</button>
			</div>
		</div>
	</div>
</div>
