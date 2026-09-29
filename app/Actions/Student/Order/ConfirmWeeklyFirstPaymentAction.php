<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmWeeklyFirstPaymentAction
{
    public function __construct(
        protected ConfirmCalendarItemsAction $confirmCalendarItems,
    ) {}

    /**
     * Record the first weekly payment (taken at Stripe Checkout) and confirm the
     * booking: the order becomes active, the paid week BOOKED and the rest RESERVED.
     *
     * Idempotent — returns true only for the call that confirmed the order, so
     * the webhook and the success-page verification can both call it safely.
     */
    public function __invoke(Order $order, ?string $chargeId): bool
    {
        $confirmed = DB::transaction(function () use ($order, $chargeId): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            // A released (cancelled) order is reported to Head Office by the caller.
            if (! $lockedOrder || ! $lockedOrder->isWeekly() || ! $lockedOrder->isPending()) {
                return false;
            }

            $lessonPayment = $lockedOrder->firstLessonPayment();

            if ($lessonPayment && ! $lessonPayment->isPaid()) {
                $lessonPayment->update([
                    'status' => PaymentStatus::PAID,
                    'stripe_charge_id' => $chargeId,
                    'paid_at' => now(),
                ]);
            }

            $lockedOrder->update(['status' => OrderStatus::ACTIVE]);

            ($this->confirmCalendarItems)($lockedOrder);

            return true;
        });

        if ($confirmed) {
            $order->refresh();

            Log::info('Weekly order confirmed by first payment', [
                'order_id' => $order->id,
                'stripe_charge_id' => $chargeId,
            ]);
        }

        return $confirmed;
    }
}
