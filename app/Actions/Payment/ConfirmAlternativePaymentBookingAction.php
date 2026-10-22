<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Actions\Student\AssignStudentOnPaidBookingAction;
use App\Enums\LessonPaymentMethod;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmAlternativePaymentBookingAction
{
    public function __construct(
        protected ConfirmCalendarItemsAction $confirmCalendarItems,
        protected AssignStudentOnPaidBookingAction $assignStudentOnPaidBooking,
    ) {}

    /**
     * Confirm a booking still awaiting its first payment because the
     * instructor has confirmed the pupil pays by an alternative method. The
     * order becomes active and its lessons pending, as after a first card
     * payment.
     *
     * Weekly: nothing is marked paid. The first lesson's payment is only
     * flagged as confirmed so it is never invoiced or chased, and it is
     * recorded as paid when the lesson is signed off.
     *
     * Pay in full: the pupil has paid the instructor for the whole block, so
     * every lesson is recorded as paid by the alternative method now. As at
     * an alternative sign-off, fees are waived (amount = the lesson price), no
     * guarantee is carried and no payout is made when the lesson is signed off.
     *
     * The caller must close the order's Stripe Checkout session first.
     * Returns true only for the call that confirmed the order.
     */
    public function __invoke(Order $order, User $confirmedBy): bool
    {
        $confirmed = DB::transaction(function () use ($order, $confirmedBy): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! $lockedOrder->isPending() || (! $lockedOrder->isWeekly() && ! $lockedOrder->isUpfront())) {
                return false;
            }

            if ($lockedOrder->isUpfront()) {
                $this->recordLessonsPaid($lockedOrder, $confirmedBy);
            } else {
                $this->flagFirstPaymentConfirmed($lockedOrder, $confirmedBy);
            }

            $lockedOrder->update(['status' => OrderStatus::ACTIVE]);

            ($this->confirmCalendarItems)($lockedOrder);

            ($this->assignStudentOnPaidBooking)($lockedOrder);

            return true;
        });

        if ($confirmed) {
            $order->refresh();

            Log::info('Order confirmed by alternative payment', [
                'order_id' => $order->id,
                'payment_mode' => $order->payment_mode->value,
                'confirmed_by_user_id' => $confirmedBy->id,
            ]);
        }

        return $confirmed;
    }

    protected function flagFirstPaymentConfirmed(Order $order, User $confirmedBy): void
    {
        $firstPayment = $order->firstLessonPayment();

        if ($firstPayment && $firstPayment->isDue()) {
            $firstPayment->update([
                'alternative_payment_confirmed_at' => now(),
                'alternative_payment_confirmed_by' => $confirmedBy->id,
            ]);
        }
    }

    /**
     * Create a PAID, alternative-method payment record for each lesson of a
     * pay-in-full order at the lesson price. Cancelled lessons and lessons
     * that already have a payment record are skipped.
     */
    protected function recordLessonsPaid(Order $order, User $confirmedBy): void
    {
        $lessons = $order->lessons()
            ->where('status', '!=', LessonStatus::CANCELLED)
            ->withExists('lessonPayment')
            ->get();

        foreach ($lessons as $lesson) {
            if ($lesson->lesson_payment_exists) {
                continue;
            }

            LessonPayment::create([
                'lesson_id' => $lesson->id,
                'amount_pence' => (int) $lesson->amount_pence,
                'test_pass_guarantee_pence' => 0,
                'status' => PaymentStatus::PAID,
                'payment_method' => LessonPaymentMethod::ALTERNATIVE,
                'due_date' => $lesson->date,
                'paid_at' => now(),
                'alternative_payment_confirmed_at' => now(),
                'alternative_payment_confirmed_by' => $confirmedBy->id,
            ]);
        }
    }
}
