<?php

namespace App\Enums;

enum RecurrencePattern: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    /**
     * Get the human-readable label for this pattern.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Does not repeat',
            self::Weekly => 'Weekly',
            self::Biweekly => 'Every 2 weeks',
            self::Monthly => 'Monthly',
        };
    }
}
