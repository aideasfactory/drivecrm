<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessType: string
{
    case SoleTrader = 'sole_trader';
    case Partnership = 'partnership';
    case LimitedCompany = 'limited_company';

    public function label(): string
    {
        return match ($this) {
            self::SoleTrader => 'Sole trader',
            self::Partnership => 'Partnership',
            self::LimitedCompany => 'Limited company',
        };
    }

    /**
     * Whether ITSA quarterly submissions can apply to this business type
     * (subject to threshold). Limited companies file Corporation Tax instead
     * and are out of MTD ITSA scope.
     */
    public function itsaCanApply(): bool
    {
        return match ($this) {
            self::SoleTrader, self::Partnership => true,
            self::LimitedCompany => false,
        };
    }
}
