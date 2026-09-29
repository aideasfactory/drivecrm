<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDraftCalendarItemsAction
{
    /**
     * Reserve calendar items for each lesson in a package.
     *
     * For each week, looks for an existing available slot matching the time range
     * and updates it to draft status. Only creates a new item if no matching slot exists.
     * Travel-time blocks are created for newly generated slots when travel time is
     * detected from the first slot in the booking. Each item records whether the
     * hold created it, so releasing an unpaid hold never leaves availability the
     * instructor did not offer.
     *
     * The whole booking is refused if any week clashes with another lesson (held,
     * reserved, booked or completed), a blocked-out period or a practical test.
     * Each day's calendar row is locked first, so two bookings for the same
     * instructor and day are checked one after the other.
     *
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    public function __invoke(
        int $instructorId,
        string $firstLessonDate,
        string $startTime,
        string $endTime,
        int $lessonsCount,
        ?int $anchorCalendarItemId = null
    ): array {
        return DB::transaction(function () use ($instructorId, $firstLessonDate, $startTime, $endTime, $lessonsCount, $anchorCalendarItemId): array {
            $calendarItemIds = [];
            $travelTimeMinutes = null;

            for ($i = 0; $i < $lessonsCount; $i++) {
                $lessonDate = Carbon::parse($firstLessonDate)->addWeeks($i);

                $calendar = Calendar::firstOrCreate([
                    'instructor_id' => $instructorId,
                    'date' => $lessonDate->toDateString(),
                ]);

                Calendar::query()->whereKey($calendar->id)->lockForUpdate()->first();

                $existingItem = null;

                if ($i === 0 && $anchorCalendarItemId) {
                    $existingItem = CalendarItem::query()
                        ->whereKey($anchorCalendarItemId)
                        ->lockForUpdate()
                        ->first();

                    if (! $existingItem || $existingItem->calendar_id !== $calendar->id) {
                        throw ValidationException::withMessages([
                            'calendar_item_id' => 'The selected diary slot could not be found.',
                        ]);
                    }

                    if (! $existingItem->isEmptyAvailability()) {
                        throw ValidationException::withMessages([
                            'calendar_item_id' => 'This diary slot is no longer available.',
                        ]);
                    }
                } else {
                    // Check for an existing available slot that matches the time range
                    $existingItem = CalendarItem::query()
                        ->where('calendar_id', $calendar->id)
                        ->where('start_time', $startTime)
                        ->where('end_time', $endTime)
                        ->where('is_available', true)
                        ->whereDoesntHave('lessons')
                        ->lockForUpdate()
                        ->first();
                }

                $this->ensureNoClash($calendar, $startTime, $endTime, $existingItem, $lessonDate, $i === 0 && $anchorCalendarItemId);

                if ($existingItem) {
                    // Capture travel time from the first existing slot to propagate to new slots
                    if ($travelTimeMinutes === null && $existingItem->travel_time_minutes) {
                        $travelTimeMinutes = $existingItem->travel_time_minutes;
                    }

                    $existingItem->update([
                        'is_available' => false,
                        'status' => CalendarItemStatus::DRAFT,
                        'created_by_hold' => false,
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
                        'created_by_hold' => true,
                    ]);

                    // Create travel-time block for newly generated slots
                    if ($travelTimeMinutes && $travelTimeMinutes > 0) {
                        $this->createTravelBlock($calendar, $calendarItem, $endTime, $travelTimeMinutes);
                    }

                    $calendarItemIds[] = $calendarItem->id;
                }
            }

            return $calendarItemIds;
        });
    }

    /**
     * Refuse the booking when the lesson time overlaps anything on the day other
     * than open availability: another lesson (held, reserved, booked or
     * completed), a blocked-out period or a practical test. Travel blocks are
     * not treated as clashes. The slot being taken over is ignored.
     *
     * @throws ValidationException
     */
    private function ensureNoClash(
        Calendar $calendar,
        string $startTime,
        string $endTime,
        ?CalendarItem $slotBeingTaken,
        Carbon $lessonDate,
        bool $isAnchorWeek
    ): void {
        $hasClash = CalendarItem::query()
            ->where('calendar_id', $calendar->id)
            ->when($slotBeingTaken, fn ($query) => $query->whereKeyNot($slotBeingTaken->id))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where(fn ($query) => $query
                ->whereIn('status', [
                    CalendarItemStatus::DRAFT,
                    CalendarItemStatus::RESERVED,
                    CalendarItemStatus::BOOKED,
                    CalendarItemStatus::COMPLETED,
                ])
                ->orWhere('item_type', CalendarItemType::PracticalTest)
                ->orWhere(fn ($query) => $query
                    ->where('item_type', CalendarItemType::Slot)
                    ->where('is_available', false)))
            ->lockForUpdate()
            ->exists();

        if (! $hasClash) {
            return;
        }

        throw ValidationException::withMessages([
            $isAnchorWeek ? 'calendar_item_id' : 'first_lesson_date' => 'This time is no longer available on '
                .$lessonDate->format('l j F Y').'. Please choose another time.',
        ]);
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
