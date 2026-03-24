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
     * Reserve calendar items for each lesson in a package.
     *
     * For each week, looks for an existing available slot matching the time range
     * and updates it to draft status. Only creates a new item if no matching slot exists.
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

            // Check for an existing available slot that matches the time range
            $existingItem = CalendarItem::query()
                ->where('calendar_id', $calendar->id)
                ->where('start_time', $startTime)
                ->where('end_time', $endTime)
                ->where('is_available', true)
                ->whereDoesntHave('lessons')
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'is_available' => false,
                    'status' => CalendarItemStatus::DRAFT,
                ]);
                $calendarItemIds[] = $existingItem->id;
            } else {
                $calendarItem = CalendarItem::create([
                    'calendar_id' => $calendar->id,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_available' => false,
                    'status' => CalendarItemStatus::DRAFT,
                ]);
                $calendarItemIds[] = $calendarItem->id;
            }
        }

        return $calendarItemIds;
    }
}
