<div class="mb-3">
    <label for="name" class="form-label">{{ __('Name') }}</label>
    <input type="text" class="form-control" id="name" name="name"
           value="{{ old('name', $category->name ?? '') }}"
           placeholder="{{ __('e.g. Billing, Account, Technical') }}" required>
</div>
<div class="mb-3">
    <label class="form-label d-block">{{ __('Status') }}</label>
    <div class="form-check form-switch">
        <input class="form-check-input coevs-switch" type="checkbox" id="edit-category-status"
               name="status" value="1" {{ old('status', isset($category) ? $category->status : 1) == 1 ? 'checked' : '' }}>
        <label class="form-check-label" for="edit-category-status">{{ __('Active') }}</label>
    </div>
</div>
<div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-primary">
        <x-icon name="check" height="24" class="me-1"/> {{ isset($category) ? __('Update Now') : __('Create Now') }}
    </button>
</div>
