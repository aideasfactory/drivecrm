<?php

declare(strict_types=1);

namespace App\Actions\Student\Status;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;

class HasUpcomingLessonsWithInstructorAction
{
    /**
     * Whether the student still has open lessons with this instructor: draft or
     * pending lessons on a pending or active order, dated today or later, or
     * banked (no date) waiting to be rebooked.
     */
    public function __invoke(Student $student, Instructor $instructor): bool
    {
        return Lesson::query()
            ->where('instructor_id', $instructor->id)
            ->whereIn('status', [LessonStatus::DRAFT, LessonStatus::PENDING])
            ->whereHas('order', fn ($query) => $query
                ->where('student_id', $student->id)
                ->whereIn('status', [OrderStatus::PENDING, OrderStatus::ACTIVE]))
            ->where(fn ($query) => $query
                ->whereNull('date')
                ->orWhere('date', '>=', today()))
            ->exists();
    }
}
