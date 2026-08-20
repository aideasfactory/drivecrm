<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FillAvailableCalendarSlotsAction
{
    /**
     * Lesson slot length in minutes (mirrors SLOT_DURATION_HOURS on the frontend).
     */
    private const SLOT_DURATION_MINUTES = 120;

    /**
     * Step between candidate start times when walking a day.
     */
    private const CANDIDATE_STEP_MINUTES = 15;

    /**
     * Fill a date range with available 2-hour lesson slots (plus travel blocks),
     * skipping any candidate slot whose window — including travel time — clashes
     * with an existing diary item.
     *
     * @param  Instructor  $instructor  The instructor
     * @param  string  $startDate  First date of the range in Y-m-d format
     * @param  int  $weeks  Number of weeks to fill from the start date
     * @param  array<int>  $days  ISO weekdays to fill (1 = Monday … 7 = Sunday)
     * @param  string  $dayStartTime  Earliest slot start each day in H:i format
     * @param  string  $dayEndTime  Latest slot end each day in H:i format
     * @param  int|null  $travelTimeMinutes  Travel time in minutes (15, 30, or 45) after each slot
     * @return Collection<int, CalendarItem> The created lesson slots (excluding travel blocks)
     */
    public function __invoke(
        Instructor $instructor,
        string $startDate,
        int $weeks,
        array $days,
        string $dayStartTime,
        string $dayEndTime,
        ?int $travelTimeMinutes = null
    ): Collection {
        $rangeStart = Carbon::parse($startDate)->startOfDay();
        $rangeEnd = $rangeStart->copy()->addWeeks($weeks)->subDay();
        $now = Carbon::now();

        $dayStartMinutes = $this->timeToMinutes($dayStartTime);
        $dayEndMinutes = $this->timeToMinutes($dayEndTime);
        $travelMinutes = $travelTimeMinutes ?: 0;

        $items = new Collection;

        for ($date = $rangeStart->copy(); $date->lte($rangeEnd); $date->addDay()) {
            if (! in_array($date->dayOfWeekIso, $days, true)) {
                continue;
            }

            if ($date->isBefore($now->copy()->startOfDay())) {
                continue;
            }

            $items = $items->merge($this->fillDay(
                $instructor,
                $date,
                $dayStartMinutes,
                $dayEndMinutes,
                $travelMinutes,
                $date->isSameDay($now) ? $now : null
            ));
        }

        return $items;
    }

    /**
     * Fill a single day with as many clash-free slots as fit in the window.
     *
     * @return Collection<int, CalendarItem>
     */
    private function fillDay(
        Instructor $instructor,
        Carbon $date,
        int $dayStartMinutes,
        int $dayEndMinutes,
        int $travelMinutes,
        ?Carbon $now
    ): Collection {
        $calendar = Calendar::firstOrCreate([
            'instructor_id' => $instructor->id,
            'date' => $date->format('Y-m-d'),
        ]);

        /** @var array<array{start: int, end: int}> $blocked */
        $blocked = $calendar->items()
            ->get(['start_time', 'end_time'])
            ->map(fn (CalendarItem $item) => [
                'start' => $this->timeToMinutes($item->start_time),
                'end' => $this->timeToMinutes($item->end_time),
            ])
            ->all();

        $candidate = $dayStartMinutes;

        // On today's date only offer slots that haven't already started.
        if ($now) {
            $nowMinutes = $now->hour * 60 + $now->minute;
            while ($candidate < $nowMinutes) {
                $candidate += self::CANDIDATE_STEP_MINUTES;
            }
        }

        $items = new Collection;

        while ($candidate + self::SLOT_DURATION_MINUTES <= $dayEndMinutes) {
            $slotEnd = $candidate + self::SLOT_DURATION_MINUTES;
            $effectiveEnd = $slotEnd + $travelMinutes;

            if ($this->overlapsAny($candidate, $effectiveEnd, $blocked)) {
                $candidate += self::CANDIDATE_STEP_MINUTES;

                continue;
            }

            $item = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $this->minutesToTime($candidate),
                'end_time' => $this->minutesToTime($slotEnd),
                'is_available' => true,
                'item_type' => CalendarItemType::Slot,
                'travel_time_minutes' => $travelMinutes > 0 ? $travelMinutes : null,
                'status' => null,
                'notes' => null,
                'unavailability_reason' => null,
            ]);

            if ($travelMinutes > 0) {
                $this->createTravelBlock($calendar, $item, $slotEnd, $travelMinutes);
            }

            $blocked[] = ['start' => $candidate, 'end' => $effectiveEnd];
            $items->push($item);

            $candidate = $effectiveEnd;
        }

        return $items;
    }

    /**
     * Whether the [start, end) window overlaps any blocked window.
     *
     * @param  array<array{start: int, end: int}>  $blocked
     */
    private function overlapsAny(int $start, int $end, array $blocked): bool
    {
        foreach ($blocked as $window) {
            if ($start < $window['end'] && $end > $window['start']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a travel-time calendar item immediately after a lesson slot.
     */
    private function createTravelBlock(
        Calendar $calendar,
        CalendarItem $parentItem,
        int $slotEndMinutes,
        int $travelMinutes
    ): CalendarItem {
        return CalendarItem::create([
            'calendar_id' => $calendar->id,
            'start_time' => $this->minutesToTime($slotEndMinutes),
            'end_time' => $this->minutesToTime($slotEndMinutes + $travelMinutes),
            'is_available' => false,
            'item_type' => CalendarItemType::Travel,
            'parent_item_id' => $parentItem->id,
            'status' => null,
            'notes' => null,
            'unavailability_reason' => 'Travel time',
        ]);
    }

    /**
     * Convert "HH:MM" or "HH:MM:SS" to minutes since midnight.
     */
    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * Convert minutes since midnight to "HH:MM".
     */
    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }
}
