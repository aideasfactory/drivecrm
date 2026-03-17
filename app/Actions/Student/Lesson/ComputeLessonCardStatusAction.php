<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonCardStatus;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Support\Carbon;

class ComputeLessonCardStatusAction
{
    /**
     * Compute the card status for a single lesson in the context of a student.
     *
     * Requires knowing the student's "next lesson" to determine current vs upcoming.
     */
    public function __invoke(Lesson $lesson, Student $student): LessonCardStatus
    {
        $today = Carbon::today();

        if ($lesson->completed_at !== null) {
            return LessonCardStatus::SignedOff;
        }

        if ($lesson->date && $lesson->date->lt($today)) {
            return LessonCardStatus::NeedsSignOff;
        }

        $nextLessonId = $this->getNextLessonId($student, $today);

        if ($nextLessonId === $lesson->id) {
            return LessonCardStatus::Current;
        }

        return LessonCardStatus::Upcoming;
    }

    /**
     * Find the ID of the student's next upcoming lesson (today or future, not completed).
     */
    private function getNextLessonId(Student $student, Carbon $today): ?int
    {
        return Lesson::query()
            ->whereIn('order_id', $student->orders()->select('id'))
            ->whereNull('completed_at')
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('start_time')
            ->value('id');
    }
}
