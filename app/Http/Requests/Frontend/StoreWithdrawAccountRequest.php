<?php

namespace App\Http\Requests\Frontend;

use App\Models\WithdrawMethod;
use App\Support\WithdrawFieldNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWithdrawAccountRequest extends FormRequest
{
    private ?WithdrawMethod $selectedWithdrawMethod = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'currency' => [
                'required',
                'string',
                Rule::in($this->activeWalletCurrencyCodes()),
            ],
            'method_id' => [
                'required',
                'integer',
                Rule::exists('withdraw_methods', 'id')->where('status', true),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $method   = $this->withdrawMethod();
                    $currency = $this->input('currency');

                    if ($method && $currency && $method->currency !== $currency) {
                        $fail(__('Selected payment method is not available for this currency.'));
                    }
                },
            ],
            'account_name' => ['required', 'string', 'max:255'],
        ], WithdrawFieldNormalizer::rules($this->withdrawMethod()?->fields ?? []));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.required'     => __('Please select a currency first.'),
            'method_id.required'    => __('Please select a payment method.'),
            'account_name.required' => __('Please enter an account name.'),
        ];
    }

    public function withdrawMethod(): ?WithdrawMethod
    {
        if ($this->selectedWithdrawMethod instanceof WithdrawMethod) {
            return $this->selectedWithdrawMethod;
        }

        if (! $this->filled('method_id')) {
            return null;
        }

        $this->selectedWithdrawMethod = WithdrawMethod::active()->find((int) $this->input('method_id'));

        return $this->selectedWithdrawMethod;
    }

    /**
     * @return array<int, string>
     */
    private function activeWalletCurrencyCodes(): array
    {
        return $this->user()?->activeWallets()
            ->pluck('currency.code')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];
    }
}
