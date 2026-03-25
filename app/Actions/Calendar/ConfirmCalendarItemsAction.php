<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Models\CalendarItem;
use App\Models\Order;
use App\Services\InstructorCalendarService;
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

        // Mirror booked status to travel items
        CalendarItem::whereIn('parent_item_id', $calendarItemIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update([
                'status' => CalendarItemStatus::BOOKED,
            ]);

        // Transition draft lessons to pending now that payment is confirmed
        $lessonsUpdated = $order->lessons()
            ->where('status', LessonStatus::DRAFT)
            ->update(['status' => LessonStatus::PENDING]);

        Log::info('ConfirmCalendarItems: Calendar items confirmed as booked', [
            'order_id' => $order->id,
            'calendar_items_updated' => $updated,
            'lessons_activated' => $lessonsUpdated,
            'calendar_item_ids' => $calendarItemIds->toArray(),
        ]);

        // Invalidate calendar cache for affected dates
        if ($updated > 0 && $order->instructor_id) {
            $dates = CalendarItem::whereIn('calendar_items.id', $calendarItemIds)
                ->join('calendars', 'calendar_items.calendar_id', '=', 'calendars.id')
                ->pluck('calendars.date')
                ->unique();

            $calendarService = app(InstructorCalendarService::class);

            foreach ($dates as $date) {
                $calendarService->invalidateCalendarCache($order->instructor_id, $date);
            }
        }

        return $updated;
    }
}
