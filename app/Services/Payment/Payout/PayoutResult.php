<?php

namespace App\Services\Payment\Payout;

use App\Enums\RemittanceStatus;

class PayoutResult
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public RemittanceStatus $status,
        public ?string $gatewayReference = null,
        public ?string $message = null,
        public array $payload = [],
    ) {}

    public function isSuccessful(): bool
    {
        return in_array($this->status, [
            RemittanceStatus::Submitted,
            RemittanceStatus::Processing,
            RemittanceStatus::Completed,
        ], true);
    }

    public function isFailed(): bool
    {
        return $this->status === RemittanceStatus::Failed;
    }
}
