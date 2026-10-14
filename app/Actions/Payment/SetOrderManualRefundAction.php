<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetOrderManualRefundAction
{
    /**
     * Admin override: tick or untick a booking as refunded after staff
     * refunded it outside the platform (e.g. directly in Stripe or by bank
     * transfer) — including bookings whose lessons were released, so there
     * are no payment rows left to mark.
     *
     * Moves no money, makes no Stripe call and leaves the order status and
     * its lesson payments untouched. Ticking records who ticked it and when;
     * unticking clears them.
     *
     * @throws ValidationException when the booking is not in a state that allows the change
     */
    public function __invoke(Order $order, bool $refunded, User $admin): Order
    {
        return DB::transaction(function () use ($order, $refunded, $admin): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->isImported()) {
                throw ValidationException::withMessages([
                    'refunded' => 'Imported bookings were not paid through the platform, so they cannot be marked as refunded.',
                ]);
            }

            if ($refunded === $order->wasManuallyRefunded()) {
                throw ValidationException::withMessages([
                    'refunded' => $refunded
                        ? 'This booking is already marked as refunded.'
                        : 'This booking is not marked as refunded.',
                ]);
            }

            $order->manually_refunded_at = $refunded ? now() : null;
            $order->manually_refunded_by_user_id = $refunded ? $admin->id : null;
            $order->save();

            return $order;
        });
    }
}
