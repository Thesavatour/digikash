<?php

namespace App\Http\Requests\Language;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PositionUpdateRequest extends FormRequest
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
            'positions'         => ['required', 'array'],
            'positions.*.id'    => ['required', 'integer', 'exists:languages,id'],
            'positions.*.order' => ['required', 'integer', 'min:1'],
        ];
    }
}
