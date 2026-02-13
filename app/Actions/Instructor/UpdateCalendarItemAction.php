<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Carbon\Carbon;

class UpdateCalendarItemAction
{
    /**
     * Update a calendar item (time slot) - handles moves and status changes.
     *
     * @param  Instructor  $instructor  The instructor who owns the calendar
     * @param  CalendarItem  $calendarItem  The calendar item to update
     * @param  string  $date  New date in Y-m-d format
     * @param  string  $startTime  New start time in H:i format
     * @param  string  $endTime  New end time in H:i format
     * @param  bool|null  $isAvailable  New availability status (null to keep unchanged)
     * @return CalendarItem The updated calendar item
     */
    public function __invoke(
        Instructor $instructor,
        CalendarItem $calendarItem,
        string $date,
        string $startTime,
        string $endTime,
        ?bool $isAvailable = null
    ): CalendarItem {
        $oldCalendar = $calendarItem->calendar;
        $newDate = Carbon::parse($date)->format('Y-m-d');

        // Check if date changed - need to move to different calendar
        if ($oldCalendar->date->format('Y-m-d') !== $newDate) {
            // Find or create calendar for the new date
            $newCalendar = Calendar::firstOrCreate([
                'instructor_id' => $instructor->id,
                'date' => $newDate,
            ]);

            $calendarItem->calendar_id = $newCalendar->id;

            // Clean up old calendar if it has no more items
            if ($oldCalendar->items()->where('id', '!=', $calendarItem->id)->count() === 0) {
                $oldCalendar->delete();
            }
        }

        // Update times
        $calendarItem->start_time = $startTime;
        $calendarItem->end_time = $endTime;

        // Update availability if provided
        if ($isAvailable !== null) {
            $calendarItem->is_available = $isAvailable;
        }

        $calendarItem->save();
        $calendarItem->load('calendar');

        return $calendarItem;
    }
}
