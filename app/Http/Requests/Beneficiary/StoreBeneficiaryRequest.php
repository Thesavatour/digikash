<?php

namespace App\Http\Requests\Beneficiary;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiaryRequest extends FormRequest
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
            'first_name'      => ['required', 'string', 'max:80'],
            'last_name'       => ['required', 'string', 'max:80'],
            'nickname'        => ['nullable', 'string', 'max:80'],
            'country_code'    => ['required', 'string', 'size:2'],
            'currency'        => ['required', 'string', 'size:3'],
            'payout_method'   => ['required', Rule::enum(PayoutMethod::class)],
            'phone_number'    => ['nullable', 'string', 'max:32', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'email'           => ['nullable', 'email', 'max:120'],
            'account_details' => ['required', 'array'],
            'relationship'    => ['nullable', 'string', 'max:64'],
            'is_favorite'     => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required'      => __('Beneficiary first name is required.'),
            'last_name.required'       => __('Beneficiary last name is required.'),
            'country_code.size'        => __('Country code must be a 2-letter ISO code.'),
            'currency.size'            => __('Currency must be a 3-letter ISO code.'),
            'payout_method.enum'       => __('Selected payout method is not supported.'),
            'account_details.required' => __('Account details are required.'),
            'phone_number.regex'       => __('Phone number must be in international format, e.g. +8801712345678.'),
        ];
    }
}
