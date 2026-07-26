<?php

namespace App\Services\Payment\Payout;

use App\Models\RemittanceTransfer;
use Illuminate\Http\Request;

interface PayoutGateway
{
    /**
     * Send funds to the beneficiary via the underlying provider.
     *
     * @return PayoutResult Carrier object with status + gateway reference + raw payload.
     */
    public function payout(RemittanceTransfer $transfer): PayoutResult;

    /**
     * Handle an inbound webhook from the provider and translate it to a
     * normalized PayoutResult so the orchestrator can update the transfer.
     */
    public function handleWebhook(Request $request): PayoutResult;

    /**
     * Poll the provider for the current status of a previously submitted payout.
     */
    public function checkStatus(RemittanceTransfer $transfer): PayoutResult;

    /**
     * Driver code (matches `remittance_corridors.payout_gateway`).
     */
    public function code(): string;
}
