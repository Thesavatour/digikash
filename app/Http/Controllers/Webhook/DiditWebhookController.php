<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Kyc\Drivers\DiditKycLiveVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Didit KYC webhook receiver.
 *
 * Always returns 2xx so Didit does not storm retries. Signature verification
 * and status mapping live in DiditKycLiveVerifier::handleCallback().
 */
class DiditWebhookController extends Controller
{
    public function __invoke(Request $request, DiditKycLiveVerifier $didit): JsonResponse
    {
        try {
            $result = $didit->handleCallback($request);

            if (($result['status'] ?? null) === 'invalid_signature') {
                return response()->json(['message' => 'Invalid signature'], 401);
            }

            return response()->json([
                'ok'      => true,
                'handled' => (bool) ($result['handled'] ?? false),
                'status'  => $result['status'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('Didit webhook handler error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['ok' => true, 'error' => 'logged']);
        }
    }
}
