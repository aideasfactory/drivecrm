<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\LessonPaymentMethod;
use App\Enums\PaymentMode;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class WaiveAlternativePaymentOrderFeesAction
{
    /**
     * Remove the booking and digital fees from a pay-in-full order the pupil
     * paid to the instructor by an alternative method. Nothing went through
     * Stripe, so no fees were charged: the order total drops by the fees and
     * every view then shows the lesson price with no fee split.
     *
     * Only upfront orders whose lesson payments are all alternative qualify.
     * Weekly orders are never changed: their card instalments are worked out
     * from the order total. Returns the amounts before and after, or null when
     * the order doesn't qualify or has no fees.
     *
     * @return array{order_id: int, booking_fee_pence: int, digital_fee_pence: int, total_before_pence: int, total_after_pence: int}|null
     */
    public function __invoke(Order $order, bool $dryRun = false): ?array
    {
        if (! $this->qualifies($order)) {
            return null;
        }

        $bookingFeePence = (int) $order->booking_fee_pence;
        $digitalFeePence = (int) $order->digital_fee_pence;
        $totalBeforePence = (int) $order->total_price_pence;
        $totalAfterPence = $totalBeforePence - $bookingFeePence - $digitalFeePence;

        if (! $dryRun) {
            $this->saveWithoutTouching($order, [
                'booking_fee_pence' => 0,
                'digital_fee_pence' => 0,
                'total_price_pence' => $totalAfterPence,
            ]);
        }

        return [
            'order_id' => $order->id,
            'booking_fee_pence' => $bookingFeePence,
            'digital_fee_pence' => $digitalFeePence,
            'total_before_pence' => $totalBeforePence,
            'total_after_pence' => $totalAfterPence,
        ];
    }

    /**
     * Orders that still carry fees although every lesson payment on them was
     * made by an alternative method.
     *
     * @return Builder<Order>
     */
    public function qualifyingOrders(): Builder
    {
        return Order::query()
            ->where('payment_mode', PaymentMode::UPFRONT)
            ->whereNotNull('total_price_pence')
            ->where(fn (Builder $query) => $query->where('booking_fee_pence', '>', 0)->orWhere('digital_fee_pence', '>', 0))
            ->whereHas('lessons.lessonPayment', fn (Builder $query) => $query->where('payment_method', LessonPaymentMethod::ALTERNATIVE))
            ->whereDoesntHave('lessons.lessonPayment', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('payment_method')->orWhere('payment_method', '!=', LessonPaymentMethod::ALTERNATIVE)
            ));
    }

    /**
     * Write the fee fields only: `updated_at` is left as it was and no model
     * events fire, so nothing downstream sees the order as changed.
     *
     * @param  array<string, int>  $attributes
     */
    protected function saveWithoutTouching(Order $order, array $attributes): void
    {
        $order->timestamps = false;

        try {
            $order->forceFill($attributes)->saveQuietly();
        } finally {
            $order->timestamps = true;
        }
    }

    protected function qualifies(Order $order): bool
    {
        return $this->qualifyingOrders()->whereKey($order->id)->exists();
    }
}
