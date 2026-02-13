<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Student;
use Illuminate\Support\Collection;

class GetStudentLessonsAction
{
    /**
     * Fetch all lessons for a student across all orders.
     *
     * Returns lessons with related order, instructor, calendar item,
     * lesson payment, and payout data for display.
     */
    public function __invoke(Student $student): Collection
    {
        return $student->orders()
            ->with([
                'lessons' => fn ($query) => $query
                    ->with([
                        'instructor.user:id,name',
                        'calendarItem.calendar:id,date',
                        'lessonPayment:id,lesson_id,amount_pence,status,paid_at',
                        'payout:id,lesson_id,status,amount_pence,stripe_transfer_id,paid_at',
                    ])
                    ->orderByDesc('date')
                    ->orderByDesc('start_time'),
                'package:id,name',
            ])
            ->get()
            ->flatMap(fn ($order) => $order->lessons->map(function ($lesson) use ($order) {
                return [
                    'id' => $lesson->id,
                    'order_id' => $order->id,
                    'instructor_id' => $lesson->instructor_id,
                    'instructor_name' => $lesson->instructor?->user?->name,
                    'package_name' => $order->package_name ?? $order->package?->name,
                    'amount_pence' => $lesson->amount_pence,
                    'date' => $lesson->date?->format('Y-m-d'),
                    'start_time' => $lesson->start_time?->format('H:i'),
                    'end_time' => $lesson->end_time?->format('H:i'),
                    'status' => $lesson->status->value,
                    'completed_at' => $lesson->completed_at?->toISOString(),
                    'payment_status' => $lesson->lessonPayment?->status?->value ?? ($order->isUpfront() ? 'paid' : null),
                    'payment_mode' => $order->payment_mode->value,
                    'payout_status' => $lesson->payout?->status?->value,
                    'has_payout' => $lesson->payout !== null,
                    'calendar_date' => $lesson->calendarItem?->calendar?->date?->format('Y-m-d'),
                ];
            }))
            ->sortByDesc('date')
            ->values();
    }
}
