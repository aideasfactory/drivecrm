<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
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
     * Travel-time blocks are created for newly generated slots when travel time is
     * detected from the first slot in the booking.
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
        $travelTimeMinutes = null;

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
                // Capture travel time from the first existing slot to propagate to new slots
                if ($travelTimeMinutes === null && $existingItem->travel_time_minutes) {
                    $travelTimeMinutes = $existingItem->travel_time_minutes;
                }

                $existingItem->update([
                    'is_available' => false,
                    'status' => CalendarItemStatus::DRAFT,
                ]);

                // Also mark the existing travel block as DRAFT so it gets confirmed with the lesson
                $existingItem->travelItem?->update([
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
                    'item_type' => CalendarItemType::Slot,
                    'travel_time_minutes' => $travelTimeMinutes,
                ]);

                // Create travel-time block for newly generated slots
                if ($travelTimeMinutes && $travelTimeMinutes > 0) {
                    $this->createTravelBlock($calendar, $calendarItem, $endTime, $travelTimeMinutes);
                }

                $calendarItemIds[] = $calendarItem->id;
            }
        }

        return $calendarItemIds;
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
