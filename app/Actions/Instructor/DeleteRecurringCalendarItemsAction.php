<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\CalendarItem;

class DeleteRecurringCalendarItemsAction
{
    /**
     * Delete recurring calendar items from a given item forward (this and all future).
     *
     * Only deletes items that have no booked lessons attached.
     *
     * @param  CalendarItem  $calendarItem  The item from which to start deletion
     * @return int Number of items deleted
     */
    public function __invoke(CalendarItem $calendarItem): int
    {
        if (! $calendarItem->recurrence_group_id) {
            return 0;
        }

        $calendarItem->load('calendar');
        $itemDate = $calendarItem->calendar->date;

        // Get all future items in this recurrence group (including the clicked item)
        $futureItems = CalendarItem::where('recurrence_group_id', $calendarItem->recurrence_group_id)
            ->whereHas('calendar', function ($query) use ($itemDate) {
                $query->where('date', '>=', $itemDate);
            })
            ->whereDoesntHave('lessons')
            ->get();

        $deletedCount = 0;

        foreach ($futureItems as $item) {
            $calendar = $item->calendar;

            // Delete any associated travel-time block
            if ($item->travelItem) {
                $item->travelItem->delete();
            }

            $item->delete();
            $deletedCount++;

            // Clean up empty calendars
            if ($calendar && $calendar->items()->count() === 0) {
                $calendar->delete();
            }
        }

        return $deletedCount;
    }
}
