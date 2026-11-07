<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetOffSlotLessonsAction
{
    /**
     * Get every upcoming open lesson that is not in a pre-made slot: the
     * booking found no available slot at that exact time, so it created a new
     * diary item (`created_by_hold`). Soonest first.
     *
     * @return array{rows: Collection<int, array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, instructor_name: ?string, lesson_date: string, lesson_time: string, package: ?string, is_provisional: bool}>, generated_at: string}
     */
    public function __invoke(): array
    {
        $rows = Lesson::query()
            ->whereIn('status', [LessonStatus::DRAFT, LessonStatus::PENDING])
            ->whereDate('date', '>=', CarbonImmutable::today())
            ->whereHas('calendarItem', fn (Builder $query) => $query->where('created_by_hold', true))
            ->with(['order.student', 'instructor.user'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Lesson $lesson): array => $this->row($lesson))
            ->values();

        return [
            'rows' => $rows,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @return array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, instructor_name: ?string, lesson_date: string, lesson_time: string, package: ?string, is_provisional: bool}
     */
    protected function row(Lesson $lesson): array
    {
        $student = $lesson->order?->student;

        return [
            'lesson_id' => $lesson->id,
            'student_id' => $student?->id,
            'instructor_id' => $student?->instructor_id ?? $lesson->instructor_id,
            'learner_name' => $this->studentName($student) ?? 'Unknown learner',
            'instructor_name' => $lesson->instructor?->name,
            'lesson_date' => $lesson->date->toDateString(),
            'lesson_time' => $lesson->start_time->format('H:i').' – '.$lesson->end_time->format('H:i'),
            'package' => $lesson->order?->package_name,
            'is_provisional' => $lesson->isProvisional(),
        ];
    }

    protected function studentName(?Student $student): ?string
    {
        $name = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        return $name !== '' ? $name : null;
    }
}
