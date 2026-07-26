<?php

declare(strict_types=1);

namespace App\Notifications\Marketplace;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MarketplaceOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $title,
        public readonly string $message,
        public readonly array $data = [],
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'marketplace_order',
            'orderId' => $this->orderId,
            'title'   => $this->title,
            'message' => $this->message,
            'data'    => $this->data,
        ];
    }
}
