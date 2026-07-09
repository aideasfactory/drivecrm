<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RecalculateStudentLessonNumbersAction
{
    /**
     * Renumber a student's open (non-completed, non-cancelled) lessons so that
     * student_lesson_number is chronological by lesson date/time across all of
     * the student's orders.
     *
     * Completed lessons are locked — their numbers never change. The sequence
     * for open lessons continues after the student's highest completed lesson
     * number. Cancelled lessons keep their historical number; they are excluded
     * from every lesson list so they never hold a slot in the sequence.
     *
     * @return int The number of lessons whose number changed
     */
    public function __invoke(int $studentId): int
    {
        return DB::transaction(function () use ($studentId): int {
            $highestCompletedNumber = (int) $this->studentLessons($studentId)
                ->where('status', LessonStatus::COMPLETED)
                ->max('student_lesson_number');

            $openLessons = $this->studentLessons($studentId)
                ->whereNotIn('status', [LessonStatus::COMPLETED, LessonStatus::CANCELLED])
                ->lockForUpdate()
                ->orderBy('date')
                ->orderBy('start_time')
                ->orderBy('id')
                ->get(['id', 'student_lesson_number']);

            $nextNumber = $highestCompletedNumber + 1;
            $changed = 0;

            foreach ($openLessons as $lesson) {
                if ((int) $lesson->student_lesson_number !== $nextNumber) {
                    Lesson::whereKey($lesson->id)->update(['student_lesson_number' => $nextNumber]);
                    $changed++;
                }

                $nextNumber++;
            }

            return $changed;
        });
    }

    /**
     * Base query for all of a student's lessons across every order.
     *
     * @return Builder<Lesson>
     */
    protected function studentLessons(int $studentId): Builder
    {
        return Lesson::query()->whereHas('order', fn (Builder $query) => $query->where('student_id', $studentId));
    }
}
