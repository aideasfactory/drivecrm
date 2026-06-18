<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetInvoiceDueWithin48HoursAction
{
    /**
     * Get learners whose lesson falls on the calendar date two days from today
     * (any time of day) and whose lesson payment is still due (unpaid), so they
     * can be chased manually before their lesson.
     *
     * @return array{rows: Collection<int, array{lesson_id: int, learner_name: string, learner_phone: ?string, learner_email: ?string, instructor_name: ?string, lesson_date: string, lesson_time: string, amount_due: string, amount_pence: int, due_date: ?string}>, generated_at: string, target_date: string}
     */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();
        $targetDate = $now->addDays(2)->toDateString();

        $lessons = Lesson::query()
            ->whereHas('lessonPayment', function ($query): void {
                $query->where('status', PaymentStatus::DUE->value);
            })
            ->whereNotIn('status', [
                LessonStatus::CANCELLED->value,
                LessonStatus::COMPLETED->value,
            ])
            ->whereDate('date', $targetDate)
            ->with(['order.student', 'instructor.user', 'lessonPayment'])
            ->orderBy('start_time')
            ->get();

        $rows = $lessons
            ->map(function (Lesson $lesson): array {
                $student = $lesson->order?->student;
                $payment = $lesson->lessonPayment;

                return [
                    'lesson_id' => $lesson->id,
                    'learner_name' => $student
                        ? trim($student->first_name.' '.$student->surname)
                        : 'Unknown learner',
                    'learner_phone' => $student?->phone,
                    'learner_email' => $student?->email,
                    'instructor_name' => $lesson->instructor?->name,
                    'lesson_date' => $lesson->date->toDateString(),
                    'lesson_time' => $lesson->start_time->format('H:i'),
                    'amount_due' => '£'.number_format(($payment?->amount_pence ?? 0) / 100, 2),
                    'amount_pence' => $payment?->amount_pence ?? 0,
                    'due_date' => $payment?->due_date?->format('Y-m-d'),
                ];
            })
            ->values();

        return [
            'rows' => $rows,
            'generated_at' => $now->toIso8601String(),
            'target_date' => $targetDate,
        ];
    }
}
