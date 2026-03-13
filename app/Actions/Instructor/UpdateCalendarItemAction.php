<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
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
     * @param  string|null  $notes  Notes about the slot (null to keep unchanged)
     * @param  string|null  $unavailabilityReason  Reason for unavailability (null to keep unchanged)
     * @return CalendarItem The updated calendar item
     */
    public function __invoke(
        Instructor $instructor,
        CalendarItem $calendarItem,
        string $date,
        string $startTime,
        string $endTime,
        ?bool $isAvailable = null,
        ?string $notes = null,
        ?string $unavailabilityReason = null
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

        // Update notes if provided
        if ($notes !== null) {
            $calendarItem->notes = $notes;
        }

        // Update unavailability reason if provided
        if ($unavailabilityReason !== null) {
            $calendarItem->unavailability_reason = $unavailabilityReason;
        }

        $calendarItem->save();

        // Update associated travel-time block if the slot time changed
        $travelItem = $calendarItem->travelItem;
        if ($travelItem) {
            $travelStart = Carbon::parse($calendarItem->end_time);
            $travelMinutes = $calendarItem->travel_time_minutes ?? 30;
            $travelEnd = $travelStart->copy()->addMinutes($travelMinutes);

            $travelItem->calendar_id = $calendarItem->calendar_id;
            $travelItem->start_time = $travelStart->format('H:i');
            $travelItem->end_time = $travelEnd->format('H:i');
            $travelItem->save();
        }

        $calendarItem->load('calendar');

        return $calendarItem;
    }
}
