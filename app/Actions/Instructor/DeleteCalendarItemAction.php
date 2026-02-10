<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\CalendarItem;

class DeleteCalendarItemAction
{
    /**
     * Delete a calendar item (time slot).
     *
     * @param  CalendarItem  $calendarItem  The calendar item to delete
     * @return bool True if deletion was successful
     *
     * @throws \Exception If calendar item has associated lessons
     */
    public function __invoke(CalendarItem $calendarItem): bool
    {
        // Check if calendar item has any lessons booked
        // Prevent deletion if lessons exist (data integrity)
        if ($calendarItem->lessons()->exists()) {
            throw new \Exception('Cannot delete calendar item with booked lessons');
        }

        // Delete the calendar item
        $deleted = $calendarItem->delete();

        // Optionally: Clean up calendar if it has no more items
        $calendar = $calendarItem->calendar;
        if ($calendar && $calendar->items()->count() === 0) {
            $calendar->delete();
        }

        return $deleted;
    }
}
