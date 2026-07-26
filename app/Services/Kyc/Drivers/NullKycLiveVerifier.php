<?php

namespace App\Services\Kyc\Drivers;

use App\Models\User;
use App\Services\Kyc\Contracts\KycLiveVerifier;
use Illuminate\Http\Request;

/**
 * Live verification disabled — KYC falls back to file uploads only.
 */
class NullKycLiveVerifier implements KycLiveVerifier
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function driver(): string
    {
        return 'none';
    }

    public function startSession(User $user): array
    {
        return [
            'mode'             => 'disabled',
            'session_url'      => null,
            'session_id'       => null,
            'require_selfie'   => false,
            'require_document' => false,
        ];
    }

    public function handleCallback(Request $request): array
    {
        return ['handled' => false];
    }
}
