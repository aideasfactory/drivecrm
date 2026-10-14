<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetLessonPaymentManualRefundAction
{
    /**
     * Admin override: tick or untick a paid lesson payment as refunded after
     * staff refunded it outside the platform (e.g. directly in Stripe or by
     * bank transfer).
     *
     * Moves no money and makes no Stripe call. Ticking sets the status to
     * `refunded` and records who ticked it and when; unticking puts the
     * payment back to `paid` (its `paid_at` is untouched) and clears them.
     *
     * @throws ValidationException when the payment is not in a state that allows the change
     */
    public function __invoke(LessonPayment $payment, bool $refunded, User $admin): LessonPayment
    {
        return DB::transaction(function () use ($payment, $refunded, $admin): LessonPayment {
            $payment = LessonPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($refunded) {
                if (! $payment->isPaid()) {
                    throw ValidationException::withMessages([
                        'refunded' => 'Only paid payments can be marked as refunded.',
                    ]);
                }

                $payment->status = PaymentStatus::REFUNDED;
                $payment->manually_refunded_at = now();
                $payment->manually_refunded_by_user_id = $admin->id;
            } else {
                if (! $payment->isRefunded() || ! $payment->wasManuallyRefunded()) {
                    throw ValidationException::withMessages([
                        'refunded' => 'Only payments marked as refunded by staff can be unmarked.',
                    ]);
                }

                $payment->status = PaymentStatus::PAID;
                $payment->manually_refunded_at = null;
                $payment->manually_refunded_by_user_id = null;
            }

            $payment->save();

            return $payment;
        });
    }
}
