<?php

namespace App\Http\Requests\Backend;

use App\Enums\PayoutMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemittanceCorridorRequest extends FormRequest
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
        $corridorId = $this->route('corridor')?->id;

        return [
            'name'                     => ['required', 'string', 'max:120'],
            'source_country'           => ['required', 'string', 'size:2'],
            'source_currency'          => ['required', 'string', 'size:3'],
            'destination_country'      => ['required', 'string', 'size:2'],
            'destination_currency'     => ['required', 'string', 'size:3'],
            'allowed_payout_methods'   => ['required', 'array', 'min:1'],
            'allowed_payout_methods.*' => [Rule::enum(PayoutMethod::class)],
            'payout_gateway'           => ['required', 'string', 'max:64'],
            'fx_spread_percent'        => ['required', 'numeric', 'min:0', 'max:50'],
            'fixed_fee'                => ['required', 'numeric', 'min:0'],
            'percent_fee'              => ['required', 'numeric', 'min:0', 'max:100'],
            'min_amount'               => ['required', 'numeric', 'min:0'],
            'max_amount'               => ['required', 'numeric', 'gte:min_amount'],
            'quote_ttl_minutes'        => ['required', 'integer', 'min:1', 'max:1440'],
            'required_kyc_tier'        => ['required', 'integer', 'min:0', 'max:5'],
            'status'                   => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allowed_payout_methods.required' => __('Pick at least one payout method.'),
            'max_amount.gte'                  => __('Max amount must be greater than or equal to min amount.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_country'       => strtoupper((string) $this->source_country),
            'source_currency'      => strtoupper((string) $this->source_currency),
            'destination_country'  => strtoupper((string) $this->destination_country),
            'destination_currency' => strtoupper((string) $this->destination_currency),
            'status'               => $this->boolean('status'),
        ]);
    }
}
