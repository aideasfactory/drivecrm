<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Carbon\Carbon;

class CreateCalendarItemAction
{
    /**
     * Create a new calendar item (time slot) for an instructor,
     * optionally with an associated travel-time block.
     *
     * @param  Instructor  $instructor  The instructor
     * @param  string  $date  Date in Y-m-d format
     * @param  string  $startTime  Start time in H:i format
     * @param  string  $endTime  End time in H:i format
     * @param  bool  $isAvailable  Whether the slot is available
     * @param  string|null  $notes  Optional notes about the slot
     * @param  string|null  $unavailabilityReason  Reason for unavailability (when is_available = false)
     * @param  int|null  $travelTimeMinutes  Travel time in minutes (15, 30, or 45) to create after the slot
     * @return CalendarItem The created calendar item
     */
    public function __invoke(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime,
        bool $isAvailable = true,
        ?string $notes = null,
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null
    ): CalendarItem {
        // Find or create calendar for this date
        $calendar = Calendar::firstOrCreate(
            [
                'instructor_id' => $instructor->id,
                'date' => Carbon::parse($date)->format('Y-m-d'),
            ]
        );

        // Create the calendar item
        $calendarItem = CalendarItem::create([
            'calendar_id' => $calendar->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => $isAvailable,
            'item_type' => CalendarItemType::Slot,
            'travel_time_minutes' => $travelTimeMinutes,
            'status' => null,
            'notes' => $notes,
            'unavailability_reason' => $unavailabilityReason,
        ]);

        // Create travel-time block if requested and slot is available
        if ($travelTimeMinutes && $travelTimeMinutes > 0 && $isAvailable) {
            $this->createTravelBlock($calendar, $calendarItem, $endTime, $travelTimeMinutes);
        }

        // Load the calendar relationship for convenience
        $calendarItem->load('calendar');

        return $calendarItem;
    }

    /**
     * Create a travel-time calendar item immediately after a lesson slot.
     */
    private function createTravelBlock(
        Calendar $calendar,
        CalendarItem $parentItem,
        string $slotEndTime,
        int $travelMinutes
    ): CalendarItem {
        $travelStart = Carbon::parse($slotEndTime);
        $travelEnd = $travelStart->copy()->addMinutes($travelMinutes);

        return CalendarItem::create([
            'calendar_id' => $calendar->id,
            'start_time' => $travelStart->format('H:i'),
            'end_time' => $travelEnd->format('H:i'),
            'is_available' => false,
            'item_type' => CalendarItemType::Travel,
            'parent_item_id' => $parentItem->id,
            'status' => null,
            'notes' => null,
            'unavailability_reason' => 'Travel time',
        ]);
    }
}
