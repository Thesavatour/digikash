<?php

namespace App\Http\Resources;

use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Beneficiary
 */
class BeneficiaryResource extends JsonResource
{
    /**
     * Field-by-field masking map. Anything not listed is returned as-is.
     * Numeric values get last-4 masked behind bullets; phone keeps the
     * country code + last 4; everything else is fully masked.
     *
     * @var array<string, string>
     */
    private const MASK_RULES = [
        'account_number' => 'last4',
        'card_number'    => 'last4',
        'iban'           => 'last4',
        'wallet_uuid'    => 'last4',
        'phone_number'   => 'phone',
        'cvv'            => 'full',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'full_name'       => $this->full_name,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'nickname'        => $this->nickname,
            'country_code'    => $this->country_code,
            'currency'        => $this->currency,
            'payout_method'   => $this->payout_method?->value,
            'payout_label'    => $this->payout_method?->label(),
            'phone_number'    => $this->phone_number,
            'email'           => $this->email,
            'masked_account'  => $this->masked_account,
            'account_details' => $this->maskAccountDetails($this->account_details ?? []),
            'relationship'    => $this->relationship,
            'is_favorite'     => (bool) $this->is_favorite,
            'is_verified'     => (bool) $this->is_verified,
            'last_used_at'    => $this->last_used_at?->toIso8601String(),
        ];
    }

    /**
     * Mask any sensitive keys in the beneficiary's account_details JSON
     * before returning over the API. Non-sensitive keys (bank_name,
     * branch, provider, etc.) are returned as-is so the UI can render
     * the human-readable label.
     *
     * @param  array<string, mixed> $details
     * @return array<string, mixed>
     */
    protected function maskAccountDetails(array $details): array
    {
        $out = [];

        foreach ($details as $key => $value) {
            $rule      = self::MASK_RULES[$key] ?? null;
            $out[$key] = match ($rule) {
                'last4' => $this->maskLast4((string) $value),
                'phone' => $this->maskPhone((string) $value),
                'full'  => str_repeat('•', max(0, strlen((string) $value))),
                default => $value,
            };
        }

        return $out;
    }

    protected function maskLast4(string $value): string
    {
        if (strlen($value) <= 4) {
            return $value;
        }

        return str_repeat('•', max(0, strlen($value) - 4)).substr($value, -4);
    }

    protected function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) <= 4) {
            return $value;
        }

        $prefix = str_starts_with($value, '+') ? '+' : '';
        $cc     = substr($digits, 0, max(1, strlen($digits) - 8));
        $tail   = substr($digits, -4);

        return $prefix.$cc.' '.str_repeat('•', 4).' '.$tail;
    }
}
