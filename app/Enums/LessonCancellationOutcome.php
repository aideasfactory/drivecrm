<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happens to the money for a cancelled lesson.
 */
enum LessonCancellationOutcome: string
{
    /** The lesson was paid for: Head Office reviews it and refunds the pupil or pays the instructor by hand. */
    case Refunded = 'refunded';

    /** The lesson was never paid for, so there is nothing to refund or pay out. */
    case Unpaid = 'unpaid';

    /**
     * The label recorded on the instructor's profile.
     */
    public function noteLabel(): string
    {
        return match ($this) {
            self::Refunded => 'Cancelled/Refund review',
            self::Unpaid => 'Cancelled/Unpaid',
        };
    }
}
