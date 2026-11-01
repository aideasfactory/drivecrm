<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\LessonStatus;
use App\Models\Instructor;
use App\Models\Lesson;

class CountInstructorUpcomingLessonsAction
{
    /**
     * Count the instructor's booked lessons from today onwards (pending, dated).
     * Banked lessons have no date, so they are not counted.
     */
    public function __invoke(Instructor $instructor): int
    {
        return Lesson::query()
            ->where('instructor_id', $instructor->id)
            ->where('status', LessonStatus::PENDING)
            ->whereNotNull('date')
            ->whereDate('date', '>=', today())
            ->count();
    }
}
