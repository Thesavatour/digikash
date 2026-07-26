<?php

namespace App\Http\Requests\Backend\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaStorageProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:120'],
            'slug'                    => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:media_storage_providers,slug'],
            'driver'                  => ['required', Rule::in(['local', 's3'])],
            'visibility'              => ['required', Rule::in(['public', 'private'])],
            'prefix'                  => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-\/]+$/'],
            'root'                    => ['nullable', 'string', 'max:500'],
            'url'                     => ['nullable', 'url', 'max:500'],
            'bucket'                  => ['required_if:driver,s3', 'nullable', 'string', 'max:255'],
            'region'                  => ['required_if:driver,s3', 'nullable', 'string', 'max:100'],
            'endpoint'                => ['nullable', 'url', 'max:500'],
            'access_key'              => ['required_if:driver,s3', 'nullable', 'string', 'max:500'],
            'secret_key'              => ['required_if:driver,s3', 'nullable', 'string', 'max:500'],
            'use_path_style_endpoint' => ['nullable', 'boolean'],
            'is_active'               => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'use_path_style_endpoint' => $this->boolean('use_path_style_endpoint'),
            'is_active'               => $this->boolean('is_active'),
        ]);
    }
}
