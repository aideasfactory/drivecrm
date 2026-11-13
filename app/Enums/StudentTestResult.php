<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentTestResult: string
{
    /** Booked and not yet sat (or no result recorded). */
    case Booked = 'booked';

    case Pass = 'pass';

    case Fail = 'fail';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Pass => 'Passed',
            self::Fail => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }
}
