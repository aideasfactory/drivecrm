<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ConfirmCalendarItemsAction
{
    /**
     * Transition calendar items from DRAFT to BOOKED for a confirmed upfront payment order.
     *
     * Called after Stripe confirms successful payment (via webhook, success callback, or API verification).
     */
    public function __invoke(Order $order): int
    {
        $calendarItemIds = $order->lessons()
            ->whereNotNull('calendar_item_id')
            ->pluck('calendar_item_id');

        if ($calendarItemIds->isEmpty()) {
            Log::info('ConfirmCalendarItems: No calendar items to confirm', [
                'order_id' => $order->id,
            ]);

            return 0;
        }

        $updated = CalendarItem::whereIn('id', $calendarItemIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update([
                'status' => CalendarItemStatus::BOOKED,
                'is_available' => false,
            ]);

        Log::info('ConfirmCalendarItems: Calendar items confirmed as booked', [
            'order_id' => $order->id,
            'calendar_items_updated' => $updated,
            'calendar_item_ids' => $calendarItemIds->toArray(),
        ]);

        return $updated;
    }
}
