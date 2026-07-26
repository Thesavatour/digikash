<?php

namespace App\Http\Requests\Language;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TranslateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'filter'   => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', 'max:12'],
            'group'    => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
