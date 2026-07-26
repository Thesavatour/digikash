<?php

declare(strict_types=1);

namespace App\Enums\Marketplace;

enum VendorStatus: string
{
    case PENDING   = 'pending';
    case ACTIVE    = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => __('Pending Review'),
            self::ACTIVE    => __('Active'),
            self::SUSPENDED => __('Suspended'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::PENDING   => 'warning',
            self::ACTIVE    => 'success',
            self::SUSPENDED => 'danger',
        };
    }

    public function badgeClass(): string
    {
        return 'badge bg-'.$this->badgeColor();
    }
}
