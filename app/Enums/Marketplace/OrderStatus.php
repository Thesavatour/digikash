<?php

declare(strict_types=1);

namespace App\Enums\Marketplace;

enum OrderStatus: string
{
    case PENDING   = 'pending';
    case RELEASED  = 'released';
    case REFUNDED  = 'refunded';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => __('Pending'),
            self::RELEASED  => __('Released to Vendor'),
            self::REFUNDED  => __('Refunded'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING   => 'warning',
            self::RELEASED  => 'success',
            self::REFUNDED  => 'info',
            self::CANCELLED => 'danger',
        };
    }

    public function badgeClass(): string
    {
        return 'badge bg-'.$this->badgeColor();
    }
}
