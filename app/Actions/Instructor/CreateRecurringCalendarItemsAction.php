<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
use App\Enums\RecurrencePattern;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CreateRecurringCalendarItemsAction
{
    /**
     * Default horizon when no end date is provided (6 months).
     */
    private const DEFAULT_HORIZON_MONTHS = 6;

    /**
     * Create recurring calendar items for an instructor.
     *
     * @param  Instructor  $instructor  The instructor
     * @param  string  $date  Starting date in Y-m-d format
     * @param  string  $startTime  Start time in H:i format
     * @param  string  $endTime  End time in H:i format
     * @param  RecurrencePattern  $pattern  The recurrence pattern
     * @param  string|null  $recurrenceEndDate  End date for recurrence (null = default horizon)
     * @param  bool  $isAvailable  Whether the slots are available
     * @param  string|null  $notes  Optional notes
     * @param  string|null  $unavailabilityReason  Reason for unavailability
     * @param  int|null  $travelTimeMinutes  Travel time in minutes (15, 30, or 45) to create after each slot
     * @return Collection<int, CalendarItem> The created calendar items
     */
    public function __invoke(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime,
        RecurrencePattern $pattern,
        ?string $recurrenceEndDate = null,
        bool $isAvailable = true,
        ?string $notes = null,
        ?string $unavailabilityReason = null,
        ?int $travelTimeMinutes = null
    ): Collection {
        $startDate = Carbon::parse($date);
        $endDate = $recurrenceEndDate
            ? Carbon::parse($recurrenceEndDate)
            : $startDate->copy()->addMonths(self::DEFAULT_HORIZON_MONTHS);

        $groupId = Str::uuid()->toString();
        $dates = $this->generateOccurrenceDates($startDate, $endDate, $pattern);
        $items = new Collection;

        foreach ($dates as $occurrenceDate) {
            $calendar = Calendar::firstOrCreate([
                'instructor_id' => $instructor->id,
                'date' => $occurrenceDate->format('Y-m-d'),
            ]);

            $item = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => $isAvailable,
                'item_type' => CalendarItemType::Slot,
                'travel_time_minutes' => $travelTimeMinutes,
                'status' => null,
                'notes' => $notes,
                'unavailability_reason' => $unavailabilityReason,
                'recurrence_pattern' => $pattern->value,
                'recurrence_end_date' => $endDate->format('Y-m-d'),
                'recurrence_group_id' => $groupId,
            ]);

            if ($travelTimeMinutes && $travelTimeMinutes > 0 && $isAvailable) {
                $this->createTravelBlock($calendar, $item, $endTime, $travelTimeMinutes);
            }

            $items->push($item);
        }

        return $items;
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

    /**
     * Generate all occurrence dates for a recurrence pattern.
     *
     * @return Collection<int, Carbon>
     */
    private function generateOccurrenceDates(Carbon $start, Carbon $end, RecurrencePattern $pattern): Collection
    {
        $dates = new Collection;
        $current = $start->copy();

        while ($current->lte($end)) {
            $dates->push($current->copy());

            $current = match ($pattern) {
                RecurrencePattern::Weekly => $current->addWeek(),
                RecurrencePattern::Biweekly => $current->addWeeks(2),
                RecurrencePattern::Monthly => $current->addMonth(),
                default => $current->addDay(), // Should not happen, but safety fallback
            };
        }

        return $dates;
    }
}
