<?php

namespace App\Http\Requests\Backend\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaAssetRequest extends FormRequest
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
            'title'    => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption'  => ['nullable', 'string', 'max:1000'],
            'folder'   => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_\-\/ ]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'folder.regex' => __('Folder names may only contain letters, numbers, spaces, dashes, underscores, and slashes.'),
        ];
    }
}
