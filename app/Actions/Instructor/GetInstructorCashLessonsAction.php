<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Models\Instructor;
use App\Models\Lesson;
use Illuminate\Support\Collection;

class GetInstructorCashLessonsAction
{
    /**
     * Signed-off lessons on an instructor's cash orders, newest first. Cash
     * lessons have no payout (the pupil paid the instructor directly), so this
     * is how they show in the instructor's reports.
     *
     * @return Collection<int, array{lesson_id: int, amount_pence: int, formatted_amount: string, student_name: string|null, package_name: string|null, lesson_date: string|null, lesson_start_time: string|null, lesson_end_time: string|null, completed_at: string|null}>
     */
    public function __invoke(Instructor $instructor): Collection
    {
        return Lesson::query()
            ->where('instructor_id', $instructor->id)
            ->where('status', LessonStatus::COMPLETED)
            ->whereHas('order', fn ($query) => $query->where('payment_mode', PaymentMode::CASH))
            ->with(['order.student', 'lessonPayment'])
            ->orderByDesc('completed_at')
            ->get()
            ->map(function (Lesson $lesson): array {
                $student = $lesson->order?->student;
                $amountPence = (int) ($lesson->lessonPayment?->amount_pence ?? $lesson->amount_pence);

                return [
                    'lesson_id' => $lesson->id,
                    'amount_pence' => $amountPence,
                    'formatted_amount' => '£'.number_format($amountPence / 100, 2),
                    'student_name' => $student ? trim($student->first_name.' '.$student->surname) : null,
                    'package_name' => $lesson->order?->package_name,
                    'lesson_date' => $lesson->date?->format('Y-m-d'),
                    'lesson_start_time' => $lesson->start_time?->format('H:i'),
                    'lesson_end_time' => $lesson->end_time?->format('H:i'),
                    'completed_at' => $lesson->completed_at?->toIso8601String(),
                ];
            });
    }
}
