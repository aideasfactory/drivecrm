<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Student\Checklist\ClearPracticalTestDateFromChecklistAction;
use App\Models\CalendarItem;

class DeleteCalendarItemAction
{
    public function __construct(
        protected ClearPracticalTestDateFromChecklistAction $clearPracticalTestDateFromChecklist
    ) {}

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

        // Capture the assigned student before deletion so we can clear their
        // practical-test checklist date once the slot is gone.
        $practicalTestStudent = ($calendarItem->isPracticalTest() && $calendarItem->student_id !== null)
            ? $calendarItem->student
            : null;

        // Delete any associated travel-time block
        if ($calendarItem->travelItem) {
            $calendarItem->travelItem->delete();
        }

        // Delete the calendar item
        $deleted = $calendarItem->delete();

        // Carry the removal over to the student's practical-test checklist item.
        if ($deleted && $practicalTestStudent) {
            ($this->clearPracticalTestDateFromChecklist)($practicalTestStudent);
        }

        // Optionally: Clean up calendar if it has no more items
        $calendar = $calendarItem->calendar;
        if ($calendar && $calendar->items()->count() === 0) {
            $calendar->delete();
        }

        return $deleted;
    }
}
