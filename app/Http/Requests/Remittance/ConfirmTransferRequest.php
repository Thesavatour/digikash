<?php

namespace App\Http\Requests\Remittance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmTransferRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'quote_id' => [
                'required',
                Rule::exists('remittance_quotes', 'quote_id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'beneficiary_id' => [
                'required',
                'integer',
                Rule::exists('beneficiaries', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'purpose'         => ['nullable', 'string', 'max:120'],
            'source_of_funds' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quote_id.exists'       => __('Quote has expired or does not belong to you.'),
            'beneficiary_id.exists' => __('Selected beneficiary is invalid.'),
            'wallet_id.exists'      => __('Selected wallet is invalid.'),
        ];
    }
}
