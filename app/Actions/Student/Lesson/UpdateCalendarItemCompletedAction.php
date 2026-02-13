<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\CalendarItemStatus;
use App\Models\Lesson;

class UpdateCalendarItemCompletedAction
{
    /**
     * Update the calendar item associated with a lesson to completed status.
     */
    public function __invoke(Lesson $lesson): void
    {
        if (! $lesson->calendarItem) {
            return;
        }

        $lesson->calendarItem->status = CalendarItemStatus::COMPLETED;
        $lesson->calendarItem->save();
    }
}
