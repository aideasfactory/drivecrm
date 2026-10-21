<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\CalendarItem;
use App\Models\Student;

class DescribeBlockingCalendarItemAction
{
    /**
     * A short staff-facing label for something a booking clashes with, e.g.
     * "Already booked: Jack Smith (10:00–11:00)" or
     * "Blocked time: Holiday (09:00–17:00)". Expects the relations loaded by
     * FindBlockingCalendarItemsAction.
     */
    public function __invoke(CalendarItem $item): string
    {
        $time = substr((string) $item->start_time, 0, 5).'–'.substr((string) $item->end_time, 0, 5);
        $lesson = $item->isTravel() ? $item->parentItem?->lessons->first() : $item->lessons->first();
        $pupil = $this->studentName($lesson?->order?->student);

        $label = match (true) {
            $item->isPracticalTest() => 'Practical test'.(($name = $this->studentName($item->student)) ? ': '.$name : ''),
            $item->isTravel() => 'Travel time'.($pupil ? ': '.$pupil : ''),
            $pupil !== null => 'Already booked: '.$pupil,
            $item->status !== null => 'Already booked',
            default => 'Blocked time'.($item->unavailability_reason ? ': '.$item->unavailability_reason : ''),
        };

        return "{$label} ({$time})";
    }

    protected function studentName(?Student $student): ?string
    {
        $name = trim(($student?->first_name ?? '').' '.($student?->surname ?? ''));

        return $name !== '' ? $name : null;
    }
}
