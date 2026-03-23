<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Enums\CalendarItemStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use Carbon\Carbon;

class CreateDraftCalendarItemsAction
{
    /**
     * Create draft calendar items for each lesson in a package.
     *
     * Returns an array of CalendarItem IDs, one per lesson.
     *
     * @return array<int, int>
     */
    public function __invoke(
        int $instructorId,
        string $firstLessonDate,
        string $startTime,
        string $endTime,
        int $lessonsCount
    ): array {
        $calendarItemIds = [];

        for ($i = 0; $i < $lessonsCount; $i++) {
            $lessonDate = Carbon::parse($firstLessonDate)->addWeeks($i);

            $calendar = Calendar::firstOrCreate([
                'instructor_id' => $instructorId,
                'date' => $lessonDate->toDateString(),
            ]);

            $calendarItem = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => false,
                'status' => CalendarItemStatus::DRAFT,
            ]);

            $calendarItemIds[] = $calendarItem->id;
        }

        return $calendarItemIds;
    }
}
