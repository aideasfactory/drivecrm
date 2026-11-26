<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Student\Lesson\GetUnscheduledLessonsSummaryAction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Safety net (agreed with Head Office 2026-11-26): before an instructor or
 * Head Office books new lessons for a pupil who still has unscheduled lessons
 * (booked, often already paid, with no date yet), ask whether to use those
 * instead. The booker can go ahead by resending the booking with
 * `ignore_unscheduled_lessons = true`. Pupils booking for themselves are never
 * asked: only an instructor or Head Office can schedule those lessons.
 */
final class UnscheduledLessons
{
    public const CONFIRM_FIELD = 'ignore_unscheduled_lessons';

    public const ERROR_KEY = 'unscheduled_lessons';

    public static function warningMessage(int $count, int $paidCount): string
    {
        $lessons = $count === 1 ? 'lesson' : 'lessons';

        if ($paidCount === $count) {
            return "This pupil has already booked and paid for {$count} {$lessons} they have remaining. Would you like to use these instead?";
        }

        $paid = $paidCount > 0 ? " ({$paidCount} already paid)" : '';

        return "This pupil has already booked {$count} {$lessons} they have remaining{$paid}. Would you like to use these instead?";
    }

    /**
     * Add the safety-net error to a booking request when it applies.
     */
    public static function checkBooking(Validator $validator, ?User $bookedBy, mixed $student, bool $confirmed): void
    {
        if ($confirmed || ! $student instanceof Student || $bookedBy === null || $bookedBy->isStudent()) {
            return;
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $summary = app(GetUnscheduledLessonsSummaryAction::class)($student);

        if ($summary['count'] === 0) {
            return;
        }

        $validator->errors()->add(self::ERROR_KEY, self::warningMessage($summary['count'], $summary['paid_count']));
    }
}
