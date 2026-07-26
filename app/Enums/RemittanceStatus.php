<?php

namespace App\Enums;

use ValueError;

enum RemittanceStatus: string
{
    case Draft      = 'draft';
    case Quoted     = 'quoted';
    case Pending    = 'pending';
    case Submitted  = 'submitted';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';
    case Refunded   = 'refunded';
    case Canceled   = 'canceled';

    /**
     * Get all remittance statuses as an array for dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn ($case) => $case->value, self::cases()),
            array_map(fn ($case) => $case->label(), self::cases())
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft      => __('Draft'),
            self::Quoted     => __('Quoted'),
            self::Pending    => __('Pending'),
            self::Submitted  => __('Submitted to Partner'),
            self::Processing => __('Processing'),
            self::Completed  => __('Completed'),
            self::Failed     => __('Failed'),
            self::Refunded   => __('Refunded'),
            self::Canceled   => __('Canceled'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft, self::Quoted => 'secondary',
            self::Pending    => 'warning',
            self::Submitted  => 'info',
            self::Processing => 'primary',
            self::Completed  => 'success',
            self::Failed, self::Canceled => 'danger',
            self::Refunded => 'info',
        };
    }

    public static function getBadgesColor(string|array $status): string
    {
        if (is_array($status)) {
            $status = $status[0];
        }

        try {
            return self::from($status)->badgeColor();
        } catch (ValueError) {
            return 'secondary';
        }
    }

    /**
     * Statuses that allow user-side cancellation before partner submission.
     *
     * @return array<int, self>
     */
    public static function cancelable(): array
    {
        return [self::Draft, self::Quoted, self::Pending];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Refunded, self::Canceled, self::Failed], true);
    }
}
