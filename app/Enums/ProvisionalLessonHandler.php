<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who sorts out a provisional (clashing) lesson with the pupil.
 */
enum ProvisionalLessonHandler: string
{
    /** The instructor booked the clash themselves, so they rearrange it. */
    case INSTRUCTOR = 'instructor';

    /** The pupil or the bookings team booked it, so the DRIVE team rearranges it. */
    case DRIVE_TEAM = 'drive_team';

    public function label(): string
    {
        return match ($this) {
            self::INSTRUCTOR => 'Instructor',
            self::DRIVE_TEAM => 'DRIVE team',
        };
    }
}
