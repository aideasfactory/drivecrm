<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Enums\LessonStatus;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetCancelledLessonsAction
{
    /**
     * Get lessons cancelled when a booked diary slot was removed. Cancelled
     * lessons are kept in the database (status, reason, and timestamp are set
     * by CancelBookingAction) even though the diary slot itself is deleted.
     *
     * @param  ?string  $cancelledFrom  Y-m-d — only lessons cancelled on/after this date
     * @param  ?string  $cancelledTo  Y-m-d — only lessons cancelled on/before this date
     * @param  ?string  $paymentStatus  'paid', 'due', or 'refunded'
     * @return array{rows: Collection<int, array{lesson_id: int, student_id: ?int, instructor_id: ?int, learner_name: string, learner_phone: ?string, learner_email: ?string, instructor_name: ?string, lesson_date: string, lesson_time: string, amount: string, amount_pence: int, payment_status: ?string, cancellation_reason: ?string, cancelled_at: ?string}>, generated_at: string}
     */
    public function __invoke(?string $cancelledFrom = null, ?string $cancelledTo = null, ?string $paymentStatus = null): array
    {
        $lessons = Lesson::query()
            ->where('status', LessonStatus::CANCELLED->value)
            ->when($cancelledFrom, function ($query) use ($cancelledFrom): void {
                $query->whereDate('cancelled_at', '>=', $cancelledFrom);
            })
            ->when($cancelledTo, function ($query) use ($cancelledTo): void {
                $query->whereDate('cancelled_at', '<=', $cancelledTo);
            })
            ->when($paymentStatus === 'due', function ($query): void {
                // Unpaid includes lessons with no payment record at all
                $query->where(function ($inner): void {
                    $inner->whereDoesntHave('lessonPayment')
                        ->orWhereHas('lessonPayment', function ($payment): void {
                            $payment->where('status', 'due');
                        });
                });
            })
            ->when(in_array($paymentStatus, ['paid', 'refunded'], true), function ($query) use ($paymentStatus): void {
                $query->whereHas('lessonPayment', function ($payment) use ($paymentStatus): void {
                    $payment->where('status', $paymentStatus);
                });
            })
            ->with(['order.student', 'instructor.user', 'lessonPayment'])
            ->orderByDesc('cancelled_at')
            ->orderByDesc('date')
            ->get();

        $rows = $lessons
            ->map(function (Lesson $lesson): array {
                $student = $lesson->order?->student;
                $payment = $lesson->lessonPayment;

                return [
                    'lesson_id' => $lesson->id,
                    'student_id' => $student?->id,
                    // Profile link target: the student's assigned instructor page,
                    // falling back to the lesson's instructor
                    'instructor_id' => $student?->instructor_id ?? $lesson->instructor_id,
                    'learner_name' => $student
                        ? trim($student->first_name.' '.$student->surname)
                        : 'Unknown learner',
                    'learner_phone' => $student?->phone,
                    'learner_email' => $student?->email,
                    'instructor_name' => $lesson->instructor?->name,
                    'lesson_date' => $lesson->date->toDateString(),
                    'lesson_time' => $lesson->start_time->format('H:i').' – '.$lesson->end_time->format('H:i'),
                    'amount' => '£'.number_format($lesson->amount_pence / 100, 2),
                    'amount_pence' => $lesson->amount_pence,
                    'payment_status' => $payment?->status->value,
                    'cancellation_reason' => $lesson->cancellation_reason,
                    'cancelled_at' => $lesson->cancelled_at?->toIso8601String(),
                ];
            })
            ->values();

        return [
            'rows' => $rows,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
