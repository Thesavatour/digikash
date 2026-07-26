<?php

namespace App\Services\Remittance;

use App\Enums\PayoutMethod;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class BeneficiaryService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(User $user, array $data): Beneficiary
    {
        $payoutMethod = PayoutMethod::from($data['payout_method']);

        $this->assertRequiredFields($payoutMethod, $data['account_details'] ?? []);

        return Beneficiary::create([
            'user_id'         => $user->id,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'nickname'        => $data['nickname'] ?? null,
            'country_code'    => strtoupper($data['country_code']),
            'currency'        => strtoupper($data['currency']),
            'payout_method'   => $payoutMethod,
            'phone_number'    => $data['phone_number'] ?? null,
            'email'           => $data['email']        ?? null,
            'account_details' => $data['account_details'],
            'relationship'    => $data['relationship'] ?? null,
            'is_favorite'     => (bool) ($data['is_favorite'] ?? false),
            'is_verified'     => false,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Beneficiary $beneficiary, array $data): Beneficiary
    {
        if (isset($data['payout_method'])) {
            $payoutMethod = PayoutMethod::from($data['payout_method']);
            $this->assertRequiredFields($payoutMethod, $data['account_details'] ?? $beneficiary->account_details ?? []);
            $data['payout_method'] = $payoutMethod;
        }

        $beneficiary->update($data);

        return $beneficiary->fresh();
    }

    public function delete(Beneficiary $beneficiary): void
    {
        $beneficiary->delete();
    }

    public function listForUser(User $user, ?string $countryCode = null, ?string $search = null): Collection
    {
        return Beneficiary::forUser($user->id)
            ->when($countryCode, fn ($q) => $q->where('country_code', strtoupper($countryCode)))
            ->when($search, function ($q) use ($search): void {
                $term = '%'.$search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('nickname', 'like', $term)
                        ->orWhere('phone_number', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('country_code', 'like', $term)
                        ->orWhere('currency', 'like', $term);
                });
            })
            ->orderByDesc('is_favorite')
            ->orderByDesc('last_used_at')
            ->orderBy('first_name')
            ->get();
    }

    public function touchLastUsed(Beneficiary $beneficiary): void
    {
        $beneficiary->update(['last_used_at' => now()]);
    }

    /**
     * @param array<string, mixed> $details
     *
     * @throws InvalidArgumentException
     */
    protected function assertRequiredFields(PayoutMethod $method, array $details): void
    {
        foreach ($method->requiredFields() as $field) {
            if ($field['required'] && empty($details[$field['name']])) {
                throw new InvalidArgumentException(__('The :field field is required.', ['field' => $field['label']]));
            }
        }
    }
}
