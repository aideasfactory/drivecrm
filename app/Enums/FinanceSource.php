<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an instructor finance row came from. Automatic rows are written by the
 * system from a paid payout or a signed-off cash / alternative-payment lesson
 * and are read-only in every UI and API.
 */
enum FinanceSource: string
{
    case Manual = 'manual';
    case StripePayout = 'stripe_payout';
    case CashLesson = 'cash_lesson';

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::StripePayout => 'Automatic — lesson payout',
            self::CashLesson => 'Automatic — cash / alternative lesson',
        };
    }
}
