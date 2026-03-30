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
     * @param  int|null  $travelTimeMinutes  Travel time in minutes (0, 15, 30, or 45). Pass 0 to remove travel block.
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
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null
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

        // Update travel time minutes if provided
        if ($travelTimeMinutes !== null) {
            $calendarItem->travel_time_minutes = $travelTimeMinutes > 0 ? $travelTimeMinutes : null;
        }

        $calendarItem->save();

        // Sync linked lessons when the calendar item is moved
        $this->syncLessonDates($calendarItem, $newDate, $startTime, $endTime);

        // Handle travel-time block changes
        $this->syncTravelBlock($calendarItem, $travelTimeMinutes);

        $calendarItem->load('calendar');

        return $calendarItem;
    }

    /**
     * Update linked lessons' date and times to match the moved calendar item.
     */
    private function syncLessonDates(CalendarItem $calendarItem, string $date, string $startTime, string $endTime): void
    {
        $calendarItem->lessons()->update([
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Synchronise the associated travel-time block: create, update, or remove as needed.
     */
    private function syncTravelBlock(CalendarItem $calendarItem, ?int $travelTimeMinutes): void
    {
        $travelItem = $calendarItem->travelItem;
        $effectiveAvailable = $calendarItem->is_available;
        $effectiveMinutes = $travelTimeMinutes ?? $calendarItem->travel_time_minutes;

        // Remove travel block when travel time is explicitly set to 0 or slot is unavailable
        if ($travelItem && ($travelTimeMinutes === 0 || ! $effectiveAvailable)) {
            $travelItem->delete();

            return;
        }

        // Update existing travel block
        if ($travelItem && $effectiveMinutes && $effectiveMinutes > 0) {
            $travelStart = Carbon::parse($calendarItem->end_time);
            $travelEnd = $travelStart->copy()->addMinutes($effectiveMinutes);

            $travelItem->calendar_id = $calendarItem->calendar_id;
            $travelItem->start_time = $travelStart->format('H:i');
            $travelItem->end_time = $travelEnd->format('H:i');
            $travelItem->save();

            return;
        }

        // Create new travel block if travel time was added and slot is available
        if (! $travelItem && $travelTimeMinutes && $travelTimeMinutes > 0 && $effectiveAvailable) {
            $travelStart = Carbon::parse($calendarItem->end_time);
            $travelEnd = $travelStart->copy()->addMinutes($travelTimeMinutes);

            CalendarItem::create([
                'calendar_id' => $calendarItem->calendar_id,
                'start_time' => $travelStart->format('H:i'),
                'end_time' => $travelEnd->format('H:i'),
                'is_available' => false,
                'item_type' => CalendarItemType::Travel,
                'parent_item_id' => $calendarItem->id,
                'status' => null,
                'notes' => null,
                'unavailability_reason' => 'Travel time',
            ]);
        }
    }
}
