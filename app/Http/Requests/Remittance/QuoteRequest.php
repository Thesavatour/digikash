<?php

namespace App\Http\Requests\Remittance;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
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
            'corridor_id'    => ['required', 'integer', 'exists:remittance_corridors,id'],
            'send_amount'    => ['required', 'numeric', 'min:1'],
            'payout_method'  => ['required', Rule::enum(PayoutMethod::class)],
            'beneficiary_id' => [
                'nullable',
                'integer',
                Rule::exists('beneficiaries', 'id')->where(fn ($q) => $q->where('user_id', $this->user()?->id)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'corridor_id.exists'    => __('Selected corridor does not exist.'),
            'send_amount.min'       => __('Send amount must be at least :min.'),
            'payout_method.enum'    => __('Selected payout method is not supported.'),
            'beneficiary_id.exists' => __('Selected beneficiary is invalid.'),
        ];
    }
}
