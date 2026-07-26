<?php

namespace App\Services\Kyc\Contracts;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Live identity verification driver.
 *
 * Builtin camera capture is the default. Third-party SDKs (Didit, Sumsub, …)
 * implement the same contract and return an external session URL instead of
 * relying on in-browser getUserMedia.
 */
interface KycLiveVerifier
{
    /**
     * Whether live verification is active for the current environment.
     */
    public function isEnabled(): bool;

    /**
     * Driver key stored on submissions (e.g. "builtin", "didit").
     */
    public function driver(): string;

    /**
     * How the frontend should collect live evidence.
     *
     * @return array{
     *     mode: 'camera'|'redirect'|'embed'|'disabled',
     *     session_url?: string|null,
     *     session_id?: string|null,
     *     require_selfie?: bool,
     *     require_document?: bool
     * }
     */
    public function startSession(User $user): array;

    /**
     * Handle provider webhooks / callbacks. Builtin driver is a no-op.
     *
     * @return array{handled: bool, status?: string|null, payload?: array<string, mixed>}
     */
    public function handleCallback(Request $request): array;
}
