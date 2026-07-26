<div class="mb-2">
    <div class="field-remove-row manual-field-row row g-2 align-items-center">
        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6">
            <input name="fields[{{ $key }}][name]"
                   class="form-control"
                   type="text"
                   value="{{ old('fields.'.$key.'.name', $field['name'] ?? '') }}"
                   required
                   aria-label="{{ __('Field label') }}"
                   placeholder="{{ __('e.g. Sender Number') }}">
        </div>
        <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6">
            <select name="fields[{{ $key }}][type]" class="form-select" aria-label="{{ __('Field type') }}">
                <option value="text" @selected(old('fields.'.$key.'.type', $field['type'] ?? '') == 'text')>{{ __('Input Text') }}</option>
                <option value="textarea" @selected(old('fields.'.$key.'.type', $field['type'] ?? '') == 'textarea')>{{ __('Textarea') }}</option>
                <option value="file" @selected(old('fields.'.$key.'.type', $field['type'] ?? '') == 'file')>{{ __('File Upload') }}</option>
            </select>
        </div>
        <div class="col-xl-3 col-lg-9 col-md-9 col-8">
            <select name="fields[{{ $key }}][validation]" class="form-select" aria-label="{{ __('Requirement') }}">
                <option value="required" @selected(old('fields.'.$key.'.validation', $field['validation'] ?? '') == 'required')>{{ __('Required') }}</option>
                <option value="nullable" @selected(old('fields.'.$key.'.validation', $field['validation'] ?? '') == 'nullable')>{{ __('Optional') }}</option>
            </select>
        </div>
        <div class="col-xl-1 col-lg-3 col-md-3 col-4">
            <button class="btn btn-outline-danger delete_field w-100" type="button" aria-label="{{ __('Remove field') }}" title="{{ __('Remove field') }}">
                <x-icon name="cil-trash" height="18"/>
            </button>
        </div>
    </div>
</div>
