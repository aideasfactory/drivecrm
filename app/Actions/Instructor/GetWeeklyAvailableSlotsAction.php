<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
use App\Models\CalendarItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class GetWeeklyAvailableSlotsAction
{
    /**
     * Count the diary slots each instructor has added in each of the next
     * `$weeks` rolling 7-day weeks, week 0 starting today: open "Available"
     * slots plus slots holding a lesson, so a fully booked diary is not short
     * of availability. Slots marked unavailable, travel blocks and practical
     * tests are not counted, nor slots that already started today.
     * Instructors with no slots in the window are left out; treat them as all
     * zeros.
     *
     * @return array<int, list<int>> instructor id => slot count per week
     */
    public function __invoke(int $weeks): array
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $lastDay = $today->copy()->addDays($weeks * 7 - 1);

        $dailyCounts = CalendarItem::query()
            ->where('calendar_items.item_type', CalendarItemType::Slot)
            ->where(fn (Builder $query) => $query
                ->where('calendar_items.is_available', true)
                ->orWhereHas('lessons'))
            ->join('calendars', 'calendars.id', '=', 'calendar_items.calendar_id')
            ->whereDate('calendars.date', '>=', $today->toDateString())
            ->whereDate('calendars.date', '<=', $lastDay->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereDate('calendars.date', '>', $today->toDateString())
                ->orWhere('calendar_items.start_time', '>', $now->format('H:i:s')))
            ->groupBy('calendars.instructor_id', 'calendars.date')
            ->selectRaw('calendars.instructor_id, calendars.date, COUNT(*) as slots')
            ->toBase()
            ->get();

        $slotsByInstructor = [];

        foreach ($dailyCounts as $day) {
            $week = intdiv((int) round($today->diffInDays(Carbon::parse($day->date)->startOfDay())), 7);
            $slotsByInstructor[$day->instructor_id] ??= array_fill(0, $weeks, 0);
            $slotsByInstructor[$day->instructor_id][$week] += (int) $day->slots;
        }

        return $slotsByInstructor;
    }
}
