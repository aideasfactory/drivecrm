<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Payout;
use Illuminate\Support\Collection;

class GetInstructorPayoutsAction
{
    /**
     * Get all payouts for an instructor with related lesson, order, and student data.
     *
     * @return Collection Formatted payout data
     */
    public function __invoke(Instructor $instructor): Collection
    {
        return Payout::where('instructor_id', $instructor->id)
            ->with(['lesson.order.student'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Payout $payout) {
                $lesson = $payout->lesson;
                $order = $lesson?->order;
                $student = $order?->student;

                $studentName = null;
                if ($student) {
                    $studentName = $student->first_name && $student->surname
                        ? $student->first_name.' '.$student->surname
                        : ($student->user?->name ?? 'Unknown');
                }

                return [
                    'id' => $payout->id,
                    'amount_pence' => $payout->amount_pence,
                    'formatted_amount' => $payout->formatted_amount,
                    'status' => $payout->status->value,
                    'paid_at' => $payout->paid_at?->toIso8601String(),
                    'created_at' => $payout->created_at->toIso8601String(),
                    'stripe_transfer_id' => $payout->stripe_transfer_id,
                    'student_name' => $studentName,
                    'package_name' => $order?->package_name,
                    'lesson_date' => $lesson?->date?->format('Y-m-d'),
                    'lesson_start_time' => $lesson?->start_time?->format('H:i'),
                    'lesson_end_time' => $lesson?->end_time?->format('H:i'),
                ];
            });
    }
}
