<?php

namespace App\Enums;

enum CalendarItemType: string
{
    case Slot = 'slot';
    case Travel = 'travel';
    case PracticalTest = 'practical_test';

    /**
     * Get the human-readable label for this type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Slot => 'Time Slot',
            self::Travel => 'Travel Time',
            self::PracticalTest => 'Practical Test',
        };
    }
}
