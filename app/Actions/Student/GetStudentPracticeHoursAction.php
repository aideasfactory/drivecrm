<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Enums\LessonStatus;
use App\Models\Student;
use Illuminate\Support\Carbon;

class GetStudentPracticeHoursAction
{
    /**
     * Calculate the student's completed and total practice hours.
     *
     * - completed: sum of duration (in hours) for all lessons with status = completed
     * - total: sum of duration (in hours) for ALL non-draft lessons across all orders
     *
     * Duration is derived from each lesson's start_time and end_time.
     *
     * @return array{completed: float, total: float}
     */
    public function __invoke(Student $student): array
    {
        $lessons = $student->orders()
            ->with([
                'lessons' => fn ($query) => $query
                    ->where('status', '!=', LessonStatus::DRAFT)
                    ->select(['id', 'order_id', 'start_time', 'end_time', 'status']),
            ])
            ->get()
            ->flatMap(fn ($order) => $order->lessons);

        $completedHours = 0.0;
        $totalHours = 0.0;

        foreach ($lessons as $lesson) {
            $duration = $this->calculateDurationHours($lesson->start_time, $lesson->end_time);
            $totalHours += $duration;

            if ($lesson->status === LessonStatus::COMPLETED) {
                $completedHours += $duration;
            }
        }

        return [
            'completed' => round($completedHours, 1),
            'total' => round($totalHours, 1),
        ];
    }

    /**
     * Calculate duration in hours between two time values.
     */
    private function calculateDurationHours($startTime, $endTime): float
    {
        if (! $startTime || ! $endTime) {
            return 0.0;
        }

        $start = $startTime instanceof Carbon ? $startTime : Carbon::parse($startTime);
        $end = $endTime instanceof Carbon ? $endTime : Carbon::parse($endTime);

        return max(0, $start->diffInMinutes($end) / 60);
    }
}
