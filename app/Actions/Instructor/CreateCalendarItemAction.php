<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Carbon\Carbon;

class CreateCalendarItemAction
{
    /**
     * Create a new calendar item (time slot) for an instructor.
     *
     * @param  Instructor  $instructor  The instructor
     * @param  string  $date  Date in Y-m-d format
     * @param  string  $startTime  Start time in H:i format
     * @param  string  $endTime  End time in H:i format
     * @return CalendarItem The created calendar item
     */
    public function __invoke(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime
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
            'is_available' => true,
            'status' => null, // Status is for booking state (draft/reserved/booked), not availability
        ]);

        // Load the calendar relationship for convenience
        $calendarItem->load('calendar');

        return $calendarItem;
    }
}
