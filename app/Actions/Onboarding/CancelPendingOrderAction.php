<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\CalendarItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class CancelPendingOrderAction
{
    /**
     * Cancel a pending order and release its calendar items back to available/draft.
     */
    public function __invoke(Order $order): void
    {
        if ($order->status !== OrderStatus::PENDING) {
            Log::warning('Attempted to cancel non-pending order', [
                'order_id' => $order->id,
                'status' => $order->status->value,
            ]);

            return;
        }

        // Release calendar items back to available
        $lessons = $order->lessons()->with('calendarItem')->get();

        foreach ($lessons as $lesson) {
            if ($lesson->calendarItem) {
                $lesson->calendarItem->update([
                    'status' => CalendarItemStatus::DRAFT,
                    'is_available' => true,
                ]);
            }

            // Delete lesson payments if any
            $lesson->lessonPayment?->delete();
        }

        // Delete lessons
        $order->lessons()->delete();

        // Cancel order
        $order->update(['status' => OrderStatus::CANCELLED]);

        Log::info('Cancelled pending order and released calendar items', [
            'order_id' => $order->id,
            'lessons_cancelled' => $lessons->count(),
        ]);
    }
}
