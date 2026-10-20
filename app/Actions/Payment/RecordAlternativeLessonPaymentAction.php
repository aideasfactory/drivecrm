<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\LessonPaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Models\User;

class RecordAlternativeLessonPaymentAction
{
    /**
     * Record a lesson as paid by an alternative method (e.g. cash to the
     * instructor). Booking and digital fees are waived, so the amount becomes
     * the lesson price and no guarantee is carried. The caller must have
     * stopped any open Stripe invoice first. A payment already paid is left
     * as it is.
     */
    public function __invoke(LessonPayment $payment, User $confirmedBy): LessonPayment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        $payment->loadMissing('lesson');

        $payment->update([
            'status' => PaymentStatus::PAID,
            'payment_method' => LessonPaymentMethod::ALTERNATIVE,
            'amount_pence' => (int) ($payment->lesson?->amount_pence ?? $payment->amount_pence),
            'test_pass_guarantee_pence' => 0,
            'paid_at' => now(),
            'alternative_payment_confirmed_at' => now(),
            'alternative_payment_confirmed_by' => $confirmedBy->id,
        ]);

        return $payment;
    }
}
