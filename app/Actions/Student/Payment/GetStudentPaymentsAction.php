<?php

declare(strict_types=1);

namespace App\Actions\Student\Payment;

use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Support\Collection;

class GetStudentPaymentsAction
{
    public function __invoke(Student $student): Collection
    {
        $orderIds = $student->orders()->pluck('id');

        return LessonPayment::whereHas('lesson', function ($query) use ($orderIds) {
            $query->whereIn('order_id', $orderIds);
        })
            ->with(['lesson:id,order_id,date,start_time,end_time', 'lesson.order:id,package_name,payment_mode'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (LessonPayment $payment) {
                $lesson = $payment->lesson;
                $order = $lesson?->order;

                return [
                    'id' => $payment->id,
                    'lesson_id' => $payment->lesson_id,
                    'lesson_date' => $lesson?->date?->format('Y-m-d'),
                    'lesson_time' => $lesson?->start_time?->format('H:i'),
                    'package_name' => $order?->package_name ?? 'Unknown',
                    'payment_mode' => $order?->payment_mode?->value ?? 'weekly',
                    'amount_pence' => $payment->amount_pence,
                    'status' => $payment->status->value,
                    'due_date' => $payment->due_date?->format('Y-m-d'),
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                ];
            });
    }
}
