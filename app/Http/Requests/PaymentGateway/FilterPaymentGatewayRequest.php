<?php

declare(strict_types=1);

namespace App\Http\Requests\PaymentGateway;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterPaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search'     => ['nullable', 'string', 'max:80'],
            'status'     => ['nullable', Rule::in(['active', 'inactive'])],
            'capability' => ['nullable', Rule::in(['withdraw', 'deposit_only'])],
            'currency'   => ['nullable', 'string', 'size:3'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search'     => $this->normalizeNullableString($this->input('search')),
            'status'     => $this->normalizeNullableString($this->input('status')),
            'capability' => $this->normalizeNullableString($this->input('capability')),
            'currency'   => strtoupper((string) $this->normalizeNullableString($this->input('currency'))),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
