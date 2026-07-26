<div class="modal fade" id="new-lang-modal" tabindex="-1" role="dialog" aria-labelledby="modal-default" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered lmg-lang-modal-dialog" role="document">
		<div class="modal-content lmg-lang-modal">
			<div class="modal-header lmg-lang-modal__header">
				<h2 class="h6 modal-title">{{ __('Create Language') }}</h2>
				<button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body lmg-lang-modal__body">
				<form class="lmg-lang-form" method="POST" action="{{ route('admin.language.store') }}" enctype="multipart/form-data">
					@csrf

					<div class="lmg-lang-field">
						<label for="flag" class="form-label">{{ __('Language Flag') }}</label>
						<div class="lmg-upload-field">
							<x-img name="flag"/>
						</div>
					</div>

					<div class="lmg-lang-field">
						<label class="form-label" for="language_name">{{ __('Language Name') }}</label>
						<input class="form-control" name="language_name" id="language_name" type="text" placeholder="EX: English" required>
					</div>

					<div class="lmg-lang-field">
						<label class="form-label" for="language_code">{{ __('Language Code') }}</label>
						<input class="form-control" name="language_code" id="language_code" type="text" placeholder="EX: en" required>
					</div>

					<div class="lmg-toggle-grid">
						<div class="form-check form-switch lmg-toggle-card">
							<label class="form-check-label" for="default">{{ __('Default') }}</label>
							<input class="form-check-input coevs-switch" type="checkbox" value="1" name="is_default" id="default">
						</div>
						<div class="form-check form-switch lmg-toggle-card">
							<label class="form-check-label" for="status">{{ __('Status') }}</label>
							<input class="form-check-input coevs-switch" type="checkbox" role="switch" name="status" value="1" id="status">
						</div>
						<div class="form-check form-switch lmg-toggle-card">
							<label class="form-check-label" for="is_rtl">{{ __('RTL') }}</label>
							<input class="form-check-input coevs-switch" type="checkbox" role="switch" name="is_rtl" value="1" id="is_rtl">
						</div>
					</div>

					<p class="lmg-form-hint">{{ __('Enable RTL for right-to-left languages such as Arabic, Hebrew, Persian or Urdu.') }}</p>

					<div class="lmg-lang-modal__footer">
						<button class="btn btn-primary lmg-save-btn" type="submit"><x-icon name="check" height="18" width="18"/> {{ __('Save Now') }}</button>
					</div>
				</form>
			</div>

		</div>
	</div>
</div>
