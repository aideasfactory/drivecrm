<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Actions\Student\AssignStudentOnPaidBookingAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\User;
use App\Support\AlternativePayments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmAlternativePaymentBookingAction
{
    public function __construct(
        protected ConfirmCalendarItemsAction $confirmCalendarItems,
        protected AssignStudentOnPaidBookingAction $assignStudentOnPaidBooking,
    ) {}

    /**
     * Confirm a weekly or one-off booking still awaiting its first payment
     * because the instructor has confirmed the pupil pays by an alternative
     * method. The order becomes active and its lessons pending, as after a
     * first card payment, but nothing is marked paid: the first lesson's
     * payment is only flagged as confirmed so it is never invoiced or chased.
     * A one-off has no payment record until it is paid, so a due one is
     * created for its lesson. It is recorded as paid when the lesson is
     * signed off.
     *
     * The caller must close the order's Stripe Checkout session first.
     * Returns true only for the call that confirmed the order.
     */
    public function __invoke(Order $order, User $confirmedBy): bool
    {
        $confirmed = DB::transaction(function () use ($order, $confirmedBy): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! AlternativePayments::supportsOrder($lockedOrder) || ! $lockedOrder->isPending()) {
                return false;
            }

            if ($lockedOrder->isOneOff()) {
                $this->createOneOffLessonPayment($lockedOrder);
            }

            $firstPayment = $lockedOrder->firstLessonPayment();

            if ($firstPayment && $firstPayment->isDue()) {
                $firstPayment->update([
                    'alternative_payment_confirmed_at' => now(),
                    'alternative_payment_confirmed_by' => $confirmedBy->id,
                ]);
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
                'payment_mode' => $order->payment_mode?->value,
                'confirmed_by_user_id' => $confirmedBy->id,
            ]);
        }

        return $confirmed;
    }

    /**
     * Give the one-off's lesson a due payment record for its share of the
     * fee-inclusive total, matching a weekly first payment. A lesson that
     * already has a payment record is left alone.
     */
    protected function createOneOffLessonPayment(Order $order): void
    {
        $lessons = $order->lessons()
            ->where('status', '!=', LessonStatus::CANCELLED)
            ->withExists('lessonPayment')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        foreach ($lessons->values() as $index => $lesson) {
            if ($lesson->lesson_payment_exists) {
                continue;
            }

            LessonPayment::create([
                'lesson_id' => $lesson->id,
                'amount_pence' => LessonPayment::orderShareForLesson($order, $lesson, $index, $lessons->count()),
                'test_pass_guarantee_pence' => LessonPayment::guaranteeShareForLesson($order, $index),
                'status' => PaymentStatus::DUE,
                'due_date' => $lesson->date,
            ]);
        }
    }
}
