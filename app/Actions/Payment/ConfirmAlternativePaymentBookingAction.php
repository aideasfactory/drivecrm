<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Actions\Student\AssignStudentOnPaidBookingAction;
use App\Enums\OrderStatus;
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
     * Confirm a weekly booking still awaiting its first payment because the
     * instructor has confirmed the pupil pays by an alternative method. The
     * order becomes active and its lessons pending, as after a first card
     * payment, but nothing is marked paid: the first lesson's payment is only
     * flagged as confirmed so it is never invoiced or chased. It is recorded
     * as paid when the lesson is signed off.
     *
     * The caller must close the order's Stripe Checkout session first.
     * Returns true only for the call that confirmed the order.
     */
    public function __invoke(Order $order, User $confirmedBy): bool
    {
        $confirmed = DB::transaction(function () use ($order, $confirmedBy): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! $lockedOrder->isWeekly() || ! $lockedOrder->isPending()) {
                return false;
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

            Log::info('Weekly order confirmed by alternative payment', [
                'order_id' => $order->id,
                'confirmed_by_user_id' => $confirmedBy->id,
            ]);
        }

        return $confirmed;
    }
}
