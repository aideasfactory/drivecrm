<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Student\Checklist\SyncPracticalTestDateToChecklistAction;
use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Student;
use Carbon\Carbon;

class CreateCalendarItemAction
{
    public function __construct(
        protected SyncPracticalTestDateToChecklistAction $syncPracticalTestDateToChecklist
    ) {}

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
     * @param  bool  $isPracticalTest  Whether this is a practical test slot
     * @param  int|null  $studentId  Student assigned to a practical test (carries the date to their checklist)
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
        ?int $travelTimeMinutes = null,
        bool $isPracticalTest = false,
        ?int $studentId = null
    ): CalendarItem {
        // For practical tests: calculate the full block from the test appointment time
        // Test time = startTime to endTime (1 hour)
        // Full block = 1hr before (prep) + 1hr test + 30min after (buffer)
        if ($isPracticalTest) {
            $testStart = Carbon::parse($startTime);
            $prepStart = $testStart->copy()->subMinutes(60);
            $bufferEnd = Carbon::parse($endTime)->addMinutes(30);

            // Find or create calendar for this date
            $calendar = Calendar::firstOrCreate(
                [
                    'instructor_id' => $instructor->id,
                    'date' => Carbon::parse($date)->format('Y-m-d'),
                ]
            );

            // Record the student's name in the notes so the test is identifiable at a glance.
            $student = $studentId !== null ? Student::find($studentId) : null;
            $studentName = $student ? trim($student->first_name.' '.$student->surname) : null;
            $practicalTestNotes = $studentName
                ? trim($studentName.($notes ? ' - '.$notes : ''))
                : $notes;

            $calendarItem = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $prepStart->format('H:i'),
                'end_time' => $bufferEnd->format('H:i'),
                'is_available' => false,
                'item_type' => CalendarItemType::PracticalTest,
                'status' => null,
                'student_id' => $studentId,
                'notes' => $practicalTestNotes,
                'unavailability_reason' => $unavailabilityReason ?? 'Practical Test',
            ]);

            $calendarItem->load('calendar');

            // Carry the booked test date over to the student's practical-test checklist item.
            if ($student) {
                ($this->syncPracticalTestDateToChecklist)($student, Carbon::parse($date)->format('Y-m-d'));
            }

            return $calendarItem;
        }

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
