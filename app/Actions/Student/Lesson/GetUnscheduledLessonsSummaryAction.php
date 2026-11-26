<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Support\Collection;

class GetUnscheduledLessonsSummaryAction
{
    /**
     * A pupil's unscheduled lessons: lessons in their bookings with no date yet
     * (shown to staff as "Unscheduled", stored as banked: `banked_at` set and
     * still pending). Expired ones are cancelled by `lessons:expire-banked`, so
     * they never appear here.
     *
     * A lesson counts as paid when its lesson payment is paid, or its booking
     * was paid up front or imported from the old system. Cash lessons are paid
     * at the lesson, so they only count once their payment is recorded.
     *
     * @return array{count: int, paid_count: int, lessons: Collection<int, Lesson>}
     */
    public function __invoke(Student $student): array
    {
        $lessons = Lesson::query()
            ->banked()
            ->whereHas('order', fn ($query) => $query->where('student_id', $student->id))
            ->with(['order', 'lessonPayment'])
            ->orderBy('banked_at')
            ->get();

        $paidCount = $lessons
            ->filter(fn (Lesson $lesson): bool => $lesson->isPaid()
                || ($lesson->order !== null && $lesson->order->isPrepaid() && ! $lesson->order->isCash()))
            ->count();

        return [
            'count' => $lessons->count(),
            'paid_count' => $paidCount,
            'lessons' => $lessons,
        ];
    }
}
