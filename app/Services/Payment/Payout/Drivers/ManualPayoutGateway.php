<?php

namespace App\Services\Payment\Payout\Drivers;

use App\Enums\RemittanceStatus;
use App\Models\RemittanceTransfer;
use App\Services\Payment\Payout\PayoutGateway;
use App\Services\Payment\Payout\PayoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Fallback payout driver. Marks transfers as Submitted (admin-approved
 * manually from the backend dashboard). Useful when a corridor doesn't
 * have an automated upstream partner yet.
 */
class ManualPayoutGateway implements PayoutGateway
{
    public function code(): string
    {
        return 'manual';
    }

    public function payout(RemittanceTransfer $transfer): PayoutResult
    {
        return new PayoutResult(
            status: RemittanceStatus::Submitted,
            gatewayReference: 'MAN-'.Str::upper(Str::random(10)),
            message: __('Awaiting manual admin approval.'),
            payload: ['driver' => 'manual'],
        );
    }

    public function handleWebhook(Request $request): PayoutResult
    {
        return new PayoutResult(
            status: RemittanceStatus::Processing,
            message: __('Manual driver does not receive webhooks.'),
        );
    }

    public function checkStatus(RemittanceTransfer $transfer): PayoutResult
    {
        return new PayoutResult(
            status: $transfer->status,
            gatewayReference: $transfer->gateway_reference,
        );
    }
}
