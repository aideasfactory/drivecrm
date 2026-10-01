<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happens to the money for a cancelled lesson.
 */
enum LessonCancellationOutcome: string
{
    /** The pupil is refunded; Head Office is told a refund is due. */
    case Refunded = 'refunded';

    /** Late cancellation the instructor kept: the lesson is paid out to them. */
    case Paid = 'paid';

    /** The lesson was never paid for, so there is nothing to refund or pay out. */
    case Unpaid = 'unpaid';

    /**
     * The label recorded on the instructor's profile.
     */
    public function noteLabel(): string
    {
        return match ($this) {
            self::Refunded => 'Cancelled/Refunded',
            self::Paid => 'Cancelled/Paid',
            self::Unpaid => 'Cancelled/Unpaid',
        };
    }
}
