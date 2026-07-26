<div class="modal fade" id="syncTranslationModal" tabindex="-1" aria-labelledby="syncTranslationModalLabel" aria-hidden="true"
     data-coreui-backdrop="static" data-coreui-keyboard="false">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h2 class="h6 modal-title d-inline-flex align-items-center gap-2" id="syncTranslationModalLabel">
					<x-icon name="sync" height="20" width="20"/>
					{{ __('Sync Missing Translations') }}
				</h2>
				<button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="{{ __('Close') }}" data-sync-dismiss hidden></button>
			</div>

			<div class="modal-body">

				{{-- Running state --}}
				<div data-sync-state="running">
					<p class="text-body-secondary small mb-3">
						{{ __('Adding keys that exist in the source language but are missing from the others. Please keep this window open.') }}
					</p>

					<div class="progress sync-progress mb-4" role="progressbar"
					     aria-label="{{ __('Sync progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
						<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
					</div>

					<ul class="list-unstyled sync-steps mb-0">
						<li data-step="scan" class="d-flex align-items-center gap-2">
							<span class="sync-step-marker"></span>
							<span>{{ __('Scanning source language keys') }}</span>
						</li>
						<li data-step="compare" class="d-flex align-items-center gap-2">
							<span class="sync-step-marker"></span>
							<span>{{ __('Comparing against other languages') }}</span>
						</li>
						<li data-step="write" class="d-flex align-items-center gap-2">
							<span class="sync-step-marker"></span>
							<span>{{ __('Writing missing keys to language files') }}</span>
						</li>
					</ul>
				</div>

				{{-- Success state --}}
				<div data-sync-state="success" hidden>
					<div class="text-center mb-3">
						<span class="sync-result-icon sync-result-icon--success">
							<x-icon name="check" height="34" width="34"/>
						</span>
						<h3 class="h5 mt-3 mb-1" data-sync-total-title></h3>
						<p class="text-body-secondary mb-0" data-sync-summary></p>
					</div>

					<ul class="list-group sync-breakdown" data-sync-breakdown hidden></ul>

					<div class="alert alert-success border-0 small mb-0 mt-3" data-sync-empty hidden>
						{{ __('No missing keys were found — every language is already complete.') }}
					</div>
				</div>

				{{-- Error state --}}
				<div data-sync-state="error" hidden>
					<div class="text-center">
						<span class="sync-result-icon sync-result-icon--error">!</span>
						<h3 class="h6 mt-3 mb-1">{{ __('Sync failed') }}</h3>
						<p class="text-body-secondary mb-0" data-sync-error></p>
					</div>
				</div>

			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-sync-action="close" data-coreui-dismiss="modal" hidden>
					{{ __('Close') }}
				</button>
				<button type="button" class="btn btn-primary d-inline-flex align-items-center" data-sync-action="retry" hidden>
					<x-icon name="sync" height="18" width="18" class="me-1"/>{{ __('Try again') }}
				</button>
				<button type="button" class="btn btn-primary" data-sync-action="done" hidden>
					{{ __('Done & refresh') }}
				</button>
			</div>
		</div>
	</div>
</div>
