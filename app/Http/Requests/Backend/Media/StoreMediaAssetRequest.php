<?php

namespace App\Http\Requests\Backend\Media;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
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
        $maxKilobytes = (int) setting('max_upload_size', 5) * 1024;

        return [
            'storage_provider_id' => ['nullable', 'integer', 'exists:media_storage_providers,id'],
            'files'               => ['required', 'array', 'min:1', 'max:20'],
            'files.*'             => [
                'required',
                'file',
                'max:'.$maxKilobytes,
                'mimes:jpeg,jpg,png,gif,webp,svg,ico,pdf,doc,docx,txt,csv,xml,json,ppt,pptx,ods,odt,xls,xlsx,zip,rar,mp4,mov,webm,mp3,wav',
            ],
            'folder'   => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_\-\/ ]+$/'],
            'title'    => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption'  => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => __('Select at least one media file to upload.'),
            'files.*.max'    => __('One of the selected files is larger than the configured upload limit.'),
            'folder.regex'   => __('Folder names may only contain letters, numbers, spaces, dashes, underscores, and slashes.'),
        ];
    }
}
