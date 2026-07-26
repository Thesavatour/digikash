<?php

namespace App\Http\Requests\WithdrawMethod;

use App\Enums\FixPctType;
use App\Enums\MethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isManual = $this->input('type') === MethodType::MANUAL->value;

        return [
            'logo'               => 'sometimes|required_if:type,manual|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type'               => 'required',
            'name'               => 'required',
            'payment_gateway_id' => $isManual ? ['nullable'] : ['required', 'integer', 'exists:payment_gateways,id'],
            // Manual methods are identified by their unique method_code, allowing
            // unlimited manual methods per currency. Automatic methods derive their
            // code from the gateway and get a unique suffix when the base code exists.
            'method_code' => $isManual
                ? ['required', Rule::unique('withdraw_methods', 'method_code')->ignore($this->route('method'))]
                : ['nullable'],
            'currency'        => 'required',
            'currency_symbol' => 'required',

            // New charge fields for user and merchant
            'user_charge'          => 'required|numeric|min:0',
            'user_charge_type'     => 'required|in:'.FixPctType::FIXED->value.','.FixPctType::PERCENT->value,
            'merchant_charge'      => 'required|numeric|min:0',
            'merchant_charge_type' => 'required|in:'.FixPctType::FIXED->value.','.FixPctType::PERCENT->value,
            'agent_charge'         => 'nullable|numeric|min:0',
            'agent_charge_type'    => 'nullable|in:'.FixPctType::FIXED->value.','.FixPctType::PERCENT->value,

            // Old charge fields - made optional for backward compatibility
            'charge'      => 'nullable|numeric|min:0',
            'charge_type' => 'nullable|in:'.FixPctType::FIXED->value.','.FixPctType::PERCENT->value,

            'conversion_rate_live' => 'boolean',
            'conversion_rate'      => 'required_if:conversion_rate_live,0',
            'min_withdraw'         => 'required',
            'max_withdraw'         => 'required',
            'fields'               => 'required_if:type,manual',
            'process_time_value'   => 'required_if:type,manual',
            'process_time_unit'    => 'required_if:type,manual',
            'status'               => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method_code.unique' => __('This method code is already in use. Please choose a unique method code.'),
        ];
    }
}
