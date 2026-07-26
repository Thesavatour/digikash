<?php

namespace App\Services\Payment\Payout;

use App\Services\Payment\Payout\Drivers\ManualPayoutGateway;
use Exception;
use Illuminate\Support\Facades\App;

class PayoutGatewayFactory
{
    /**
     * Resolve a payout driver by code. Mirrors PaymentGatewayFactory but
     * scoped to outbound payout (remittance) drivers.
     *
     * @throws Exception
     */
    public function getGateway(string $gatewayCode): PayoutGateway
    {
        return match ($gatewayCode) {
            'manual' => App::make(ManualPayoutGateway::class),
            default  => throw new Exception(sprintf('Unsupported payout gateway: %s', $gatewayCode)),
        };
    }
}
