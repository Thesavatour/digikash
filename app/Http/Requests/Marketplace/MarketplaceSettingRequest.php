<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketplace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarketplaceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled'             => ['required', 'boolean'],
            'commission_pct'      => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_fixed'    => ['nullable', 'numeric', 'min:0'],
            'min_order_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_order_amount'    => ['nullable', 'numeric', 'min:0', 'gt:min_order_amount'],
            'auto_release'        => ['required', 'boolean'],
            'payout_hold_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'commission_user_id'  => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_order_amount.gt'       => __('Maximum order amount must be greater than the minimum.'),
            'commission_user_id.exists' => __('The selected commission account is not a valid user.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled'      => $this->boolean('enabled'),
            'auto_release' => $this->boolean('auto_release'),
        ]);
    }
}
