<?php

declare(strict_types=1);

namespace App\Actions\Refund;

use App\Enums\RefundStatus;
use App\Models\Lesson;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Collection;

class CreateRefundsForCancelledLessonsAction
{
    /**
     * Persist a pending refund request for each paid cancelled lesson that
     * does not already have a refund row.
     *
     * @param  Collection<int, Lesson>  $paidLessons
     * @return Collection<int, Refund>
     */
    public function __invoke(Collection $paidLessons, User $actor, string $reason): Collection
    {
        $refunds = new Collection;

        foreach ($paidLessons as $lesson) {
            $existing = Refund::query()->where('lesson_id', $lesson->id)->first();

            if ($existing) {
                $refunds->push($existing);

                continue;
            }

            $lesson->loadMissing(['lessonPayment', 'order.student', 'order.instructor']);

            $amountPence = $this->amountForLesson($lesson);

            if ($amountPence <= 0) {
                continue;
            }

            $refunds->push(Refund::query()->create([
                'lesson_id' => $lesson->id,
                'order_id' => $lesson->order_id,
                'lesson_payment_id' => $lesson->lessonPayment?->id,
                'student_id' => $lesson->order?->student_id,
                'instructor_id' => $lesson->instructor_id ?? $lesson->order?->instructor_id,
                'requested_by_user_id' => $actor->id,
                'amount_pence' => $amountPence,
                'status' => RefundStatus::PENDING,
                'reason' => $reason,
                'requested_at' => now(),
            ]));
        }

        return $refunds;
    }

    protected function amountForLesson(Lesson $lesson): int
    {
        if ($lesson->lessonPayment) {
            return (int) $lesson->lessonPayment->amount_pence;
        }

        return (int) $lesson->amount_pence;
    }
}
