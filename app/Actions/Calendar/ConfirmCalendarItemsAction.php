<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Models\CalendarItem;
use App\Models\Order;
use App\Services\InstructorCalendarService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ConfirmCalendarItemsAction
{
    /**
     * Transition an order's draft calendar items once its first payment is confirmed.
     *
     * Upfront orders: every item becomes BOOKED. Weekly orders: items for paid
     * lessons become BOOKED and the rest RESERVED — each later week turns BOOKED
     * when its invoice is paid.
     *
     * Called after Stripe confirms payment (via webhook, success callback, or API verification).
     */
    public function __invoke(Order $order): int
    {
        $lessons = $order->lessons()
            ->whereNotNull('calendar_item_id')
            ->with('lessonPayment:id,lesson_id,status')
            ->get(['id', 'calendar_item_id']);

        $calendarItemIds = $lessons->pluck('calendar_item_id');

        if ($calendarItemIds->isEmpty()) {
            Log::info('ConfirmCalendarItems: No calendar items to confirm', [
                'order_id' => $order->id,
            ]);

            return 0;
        }

        if ($order->isWeekly()) {
            $bookedIds = $lessons
                ->filter(fn ($lesson) => $lesson->lessonPayment?->status === PaymentStatus::PAID)
                ->pluck('calendar_item_id');
            $reservedIds = $calendarItemIds->diff($bookedIds);
        } else {
            $bookedIds = $calendarItemIds;
            $reservedIds = collect();
        }

        $updated = $this->transitionDraftItems($bookedIds, CalendarItemStatus::BOOKED)
            + $this->transitionDraftItems($reservedIds, CalendarItemStatus::RESERVED);

        $lessonsUpdated = $order->lessons()
            ->where('status', LessonStatus::DRAFT)
            ->update(['status' => LessonStatus::PENDING]);

        Log::info('ConfirmCalendarItems: Calendar items confirmed', [
            'order_id' => $order->id,
            'calendar_items_updated' => $updated,
            'lessons_activated' => $lessonsUpdated,
            'booked_calendar_item_ids' => $bookedIds->values()->toArray(),
            'reserved_calendar_item_ids' => $reservedIds->values()->toArray(),
        ]);

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

    /**
     * Move draft items (and their draft travel blocks) to the given status.
     *
     * @param  Collection<int, int>  $calendarItemIds
     */
    protected function transitionDraftItems(Collection $calendarItemIds, CalendarItemStatus $status): int
    {
        if ($calendarItemIds->isEmpty()) {
            return 0;
        }

        $updated = CalendarItem::whereIn('id', $calendarItemIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update([
                'status' => $status,
                'is_available' => false,
            ]);

        CalendarItem::whereIn('parent_item_id', $calendarItemIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update(['status' => $status]);

        return $updated;
    }
}
