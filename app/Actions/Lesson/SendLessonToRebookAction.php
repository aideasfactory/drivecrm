<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonCancellationReason;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\User;
use RuntimeException;

class SendLessonToRebookAction
{
    public function __construct(
        protected BankLessonsAction $bankLessons,
    ) {}

    /**
     * Make a lesson unscheduled (banked) with the given instructor, so they
     * can schedule it from their own diary. A lesson still in a diary is
     * banked first (same rules as "Rebook later": the pupil keeps it, payment
     * untouched, no payout, any open invoice voided). Used for a lesson missed
     * by a pupil's old instructor and for a lesson that clashes with the new
     * instructor's diary at a transfer.
     *
     * @throws RuntimeException When the lesson can't be banked
     */
    public function __invoke(
        Lesson $lesson,
        LessonCancellationReason $reasonCode,
        ?string $details,
        Instructor $newOwner,
        User $actor,
        bool $notifyStudent = true,
    ): Lesson {
        $lesson->loadMissing(['order.student', 'instructor.user', 'calendarItem']);

        if (! $lesson->isBanked()) {
            if (! $lesson->calendarItem) {
                throw new RuntimeException('The lesson has no diary slot to take it off.');
            }

            ($this->bankLessons)($lesson->calendarItem, $reasonCode, $details, false, $actor, $notifyStudent);
        }

        $lesson->refresh();

        if ($lesson->instructor_id === $newOwner->id) {
            return $lesson;
        }

        $fromInstructorId = $lesson->instructor_id;
        $fromInstructorName = $lesson->instructor?->name ?? "#{$fromInstructorId}";

        $lesson->update(['instructor_id' => $newOwner->id]);

        $lesson->order?->student?->logActivity(
            "Unscheduled lesson #{$lesson->id} moved from {$fromInstructorName} to {$newOwner->name}",
            'booking',
            [
                'lesson_id' => $lesson->id,
                'from_instructor_id' => $fromInstructorId,
                'to_instructor_id' => $newOwner->id,
                'by_user_id' => $actor->id,
            ],
        );

        return $lesson;
    }
}
