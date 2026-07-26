<form class="lmg-lang-form" method="POST" action="{{ route('admin.language.update', ['language' => $language->id]) }}" enctype="multipart/form-data">
    @method('PUT')
    @csrf

    <div class="lmg-lang-field">
        <label for="flag" class="form-label">{{ __('Language Flag') }}</label>
        <div class="lmg-upload-field">
            <x-img name="flag" old="{{ $language->flag }}" :ref="'coevs-language-flag'"/>
        </div>
    </div>

    <div class="lmg-lang-field">
        <label class="form-label" for="edit_language_name">{{ __('Language Name') }}</label>
        <input class="form-control" name="language_name" id="edit_language_name" value="{{ $language->name }}" type="text" placeholder="Enter Language name" required>
    </div>

    <div class="lmg-lang-field">
        <label class="form-label" for="edit_language_code">{{ __('Language Code') }}</label>
        <input class="form-control" name="language_code" id="edit_language_code" @disabled($language->code == 'en') value="{{ $language->code }}" type="text" placeholder="Enter Language Code" required>
    </div>

    <div class="lmg-toggle-grid">
        <div class="form-check form-switch lmg-toggle-card">
            <label class="form-check-label" for="edit_default">{{ __('Default') }}</label>
            <input class="form-check-input coevs-switch" type="checkbox" value="1" name="is_default" @checked($language->is_default) id="edit_default">
        </div>
        <div class="form-check form-switch lmg-toggle-card">
            <label class="form-check-label" for="edit_status">{{ __('Status') }}</label>
            <input class="form-check-input coevs-switch" type="checkbox" role="switch" name="status" @checked($language->status) value="1" id="edit_status">
        </div>
        <div class="form-check form-switch lmg-toggle-card">
            <label class="form-check-label" for="edit_is_rtl">{{ __('RTL') }}</label>
            <input class="form-check-input coevs-switch" type="checkbox" role="switch" name="is_rtl" @checked($language->is_rtl) value="1" id="edit_is_rtl">
        </div>
    </div>

    <p class="lmg-form-hint">{{ __('Enable RTL for right-to-left languages such as Arabic, Hebrew, Persian or Urdu.') }}</p>

    <div class="lmg-lang-modal__footer">
        <button class="btn btn-primary lmg-save-btn" type="submit"><x-icon name="check" height="18" width="18"/> {{ __('Save Now') }}</button>
    </div>
</form>
