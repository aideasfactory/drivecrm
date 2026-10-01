<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\Calendar\ConfirmCalendarItemsAction;
use App\Actions\Student\AssignStudentOnPaidBookingAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmUpfrontPaymentAction
{
    public function __construct(
        protected ConfirmCalendarItemsAction $confirmCalendarItems,
        protected AssignStudentOnPaidBookingAction $assignStudentOnPaidBooking,
    ) {}

    /**
     * Record a pay-in-full payment and confirm the booking: the order becomes
     * active, each lesson gets a PAID payment record for its share of the total
     * and the diary slots are booked.
     *
     * Idempotent — the order row is locked and only a still-pending upfront order
     * is confirmed, so the webhook, the success pages and the app's verify call
     * can all call it. Returns true only for the call that confirmed the order.
     */
    public function __invoke(Order $order, ?string $paymentIntentId, ?string $chargeId): bool
    {
        $confirmed = DB::transaction(function () use ($order, $paymentIntentId, $chargeId): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if (! $lockedOrder || ! $lockedOrder->isUpfront() || $lockedOrder->status !== OrderStatus::PENDING) {
                return false;
            }

            $lockedOrder->update([
                'status' => OrderStatus::ACTIVE,
                'stripe_payment_intent_id' => $paymentIntentId ?? $lockedOrder->stripe_payment_intent_id,
                'stripe_charge_id' => $chargeId ?? $lockedOrder->stripe_charge_id,
            ]);

            $this->createLessonPayments($lockedOrder);

            ($this->confirmCalendarItems)($lockedOrder);

            ($this->assignStudentOnPaidBooking)($lockedOrder);

            return true;
        });

        if ($confirmed) {
            $order->refresh();

            Log::info('Upfront order confirmed by payment', [
                'order_id' => $order->id,
                'stripe_payment_intent_id' => $paymentIntentId,
            ]);
        }

        return $confirmed;
    }

    /**
     * Create a PAID payment record for each lesson, holding its share of the
     * fee-inclusive total (any guarantee sits on the first lesson). Lessons that
     * already have a payment record are skipped.
     */
    protected function createLessonPayments(Order $order): void
    {
        $lessons = $order->lessons()
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
                'status' => PaymentStatus::PAID,
                'due_date' => $lesson->date,
                'paid_at' => now(),
            ]);
        }
    }
}
