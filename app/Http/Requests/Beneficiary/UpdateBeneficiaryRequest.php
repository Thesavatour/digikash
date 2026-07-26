<?php

namespace App\Http\Requests\Beneficiary;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $beneficiary = $this->route('beneficiary');

        return $beneficiary && $beneficiary->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name'      => ['sometimes', 'required', 'string', 'max:80'],
            'last_name'       => ['sometimes', 'required', 'string', 'max:80'],
            'nickname'        => ['nullable', 'string', 'max:80'],
            'country_code'    => ['sometimes', 'required', 'string', 'size:2'],
            'currency'        => ['sometimes', 'required', 'string', 'size:3'],
            'payout_method'   => ['sometimes', 'required', Rule::enum(PayoutMethod::class)],
            'phone_number'    => ['nullable', 'string', 'max:32', 'regex:/^\+?[1-9]\d{6,14}$/'],
            'email'           => ['nullable', 'email', 'max:120'],
            'account_details' => ['sometimes', 'required', 'array'],
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
            'phone_number.regex' => __('Phone number must be in international format, e.g. +8801712345678.'),
        ];
    }
}
