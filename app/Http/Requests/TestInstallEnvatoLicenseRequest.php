<?php

namespace App\Http\Requests;

use App\Support\EnvatoLicenseVerifier;
use App\Support\InstallationManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestInstallEnvatoLicenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The license test must verify against the real install domain, not
        // a client-supplied value, so the same domain is locked at preview
        // time and at final installation.
        $this->merge([
            'app_url' => InstallationManager::resolveInstallUrl(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_url'              => ['nullable', 'url', 'max:255'],
            'envato_purchase_code' => [
                config('installer.envato.required', true) ? 'required' : 'nullable',
                'string',
                'max:80',
                'regex:'.EnvatoLicenseVerifier::PURCHASE_CODE_PATTERN,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'envato_purchase_code.required' => __('Envato purchase code is required before installation.'),
            'envato_purchase_code.regex'    => __('Enter a valid Envato purchase code from your license certificate.'),
        ];
    }
}
