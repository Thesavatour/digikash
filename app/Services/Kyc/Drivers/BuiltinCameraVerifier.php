<?php

namespace App\Services\Kyc\Drivers;

use App\Models\User;
use App\Services\Kyc\Contracts\KycLiveVerifier;
use Illuminate\Http\Request;

/**
 * In-browser document photo + live video capture via getUserMedia / MediaRecorder.
 * Evidence is uploaded with the KYC form; admin reviews manually.
 */
class BuiltinCameraVerifier implements KycLiveVerifier
{
    public function isEnabled(): bool
    {
        return kyc_live_driver() === 'builtin';
    }

    public function driver(): string
    {
        return 'builtin';
    }

    public function startSession(User $user): array
    {
        return [
            'mode'             => 'camera',
            'session_url'      => null,
            'session_id'       => null,
            'require_selfie'   => (bool) config('kyc.builtin.require_selfie', true),
            'require_document' => (bool) config('kyc.builtin.require_document', true),
            'record_seconds'   => (float) config('kyc.builtin.liveness_record_seconds', 5),
        ];
    }

    public function handleCallback(Request $request): array
    {
        return ['handled' => false];
    }
}
