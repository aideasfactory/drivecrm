<?php

declare(strict_types=1);

namespace App\Actions\Refund;

use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarkRefundCompleteAction
{
    /**
     * Record that staff refunded this payment in Stripe (or otherwise) by hand.
     */
    public function __invoke(Refund $refund, User $actor): Refund
    {
        $refund = DB::transaction(function () use ($refund, $actor): Refund {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $refund->loadMissing(['lesson.order.student', 'lessonPayment', 'student']);

            if (! $refund->isPending()) {
                throw new RuntimeException('This refund has already been completed.');
            }

            $refund->status = RefundStatus::COMPLETED;
            $refund->method = RefundMethod::MANUAL;
            $refund->processed_by_user_id = $actor->id;
            $refund->completed_at = now();
            $refund->save();

            $lessonPayment = $refund->lessonPayment ?? $refund->lesson?->lessonPayment;

            if ($lessonPayment) {
                $lessonPayment->status = PaymentStatus::REFUNDED;
                $lessonPayment->save();
            }

            return $refund;
        });

        $refund = $refund->fresh(['processedBy', 'requestedBy', 'student', 'instructor', 'lesson', 'order']) ?? $refund;

        $student = $refund->student ?? $refund->lesson?->order?->student;

        if ($student) {
            $trail = $refund->paperTrail() ?? ($actor->name.' made refund on '.now()->format('d/m/Y H:i'));

            $student->logActivity(
                $trail.' for lesson #'.$refund->lesson_id.' ('.$refund->formatted_amount.') — marked complete after a manual Stripe refund',
                'payment',
                [
                    'refund_id' => $refund->id,
                    'lesson_id' => $refund->lesson_id,
                    'amount_pence' => $refund->amount_pence,
                    'method' => RefundMethod::MANUAL->value,
                    'processed_by_user_id' => $actor->id,
                ],
                $trail,
            );
        }

        return $refund;
    }
}
