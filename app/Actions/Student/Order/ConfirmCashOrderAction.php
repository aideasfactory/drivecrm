<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Actions\Student\AssignStudentOnPaidBookingAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmCashOrderAction
{
    public function __construct(
        protected ConfirmCalendarItemsAction $confirmCalendarItems,
        protected AssignStudentOnPaidBookingAction $assignStudentOnPaidBooking,
    ) {}

    /**
     * Confirm a cash order as soon as it is booked. There is no first payment
     * to wait for: the order becomes active with no payment window, every
     * diary item is BOOKED and every lesson PENDING. No lesson payments are
     * created here — each is recorded as paid in cash when its lesson is
     * signed off.
     *
     * Returns true only for the call that confirmed the order.
     */
    public function __invoke(Order $order): bool
    {
        $confirmed = DB::transaction(function () use ($order): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! $lockedOrder->isCash() || ! $lockedOrder->isPending()) {
                return false;
            }

            $lockedOrder->update([
                'status' => OrderStatus::ACTIVE,
                'payment_hold_expires_at' => null,
            ]);

            ($this->confirmCalendarItems)($lockedOrder);

            ($this->assignStudentOnPaidBooking)($lockedOrder);

            return true;
        });

        if ($confirmed) {
            $order->refresh();

            Log::info('Cash order confirmed on booking', [
                'order_id' => $order->id,
            ]);
        }

        return $confirmed;
    }
}
