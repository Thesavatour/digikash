<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class ThemeColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'colors'   => ['required', 'array'],
            'colors.*' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'colors.*.regex'    => __('Each color must be a valid 6-digit hex value, e.g. #4663EE.'),
            'colors.*.required' => __('Please choose a color for every slot.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $colors = $this->input('colors', []);

        if (is_array($colors)) {
            $this->merge([
                'colors' => collect($colors)
                    ->map(fn ($value): string => strtoupper(trim((string) $value)))
                    ->all(),
            ]);
        }
    }
}
