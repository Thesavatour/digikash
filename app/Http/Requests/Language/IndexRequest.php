<?php

namespace App\Http\Requests\Language;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
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
            'search'    => ['nullable', 'string', 'max:100'],
            'status'    => ['nullable', Rule::in(['all', 'active', 'inactive', 'default'])],
            'direction' => ['nullable', Rule::in(['all', 'ltr', 'rtl'])],
            'per_page'  => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ];
    }
}
