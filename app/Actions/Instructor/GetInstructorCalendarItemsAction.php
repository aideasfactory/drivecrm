<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Collection;

class GetInstructorCalendarItemsAction
{
    /**
     * Get calendar items for an instructor on a specific date.
     *
     * @param  bool  $availableOnly  When true, returns only truly available slots — excludes
     *                               travel/practical_test types AND any slot whose time range
     *                               overlaps with a booked/reserved/completed item on the same calendar.
     *                               When false, returns all items.
     * @param  bool  $excludeDrafts  When true, excludes items with draft status.
     * @return Collection<int, CalendarItem>
     */
    public function __invoke(Instructor $instructor, string $date, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        return $this->between($instructor, $date, $date, $availableOnly, $excludeDrafts);
    }

    /**
     * Calendar items for an inclusive date range, ordered by date then start time.
     *
     * Days with no items are omitted. Filters match the single-day query.
     *
     * @return Collection<int, CalendarItem>
     */
    public function between(Instructor $instructor, string $from, string $to, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        $calendars = Calendar::query()
            ->where('instructor_id', $instructor->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();

        $items = new Collection;

        foreach ($calendars as $calendar) {
            foreach ($this->itemsForCalendar($calendar, $availableOnly, $excludeDrafts) as $item) {
                $items->push($item);
            }
        }

        return $items;
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    private function itemsForCalendar(Calendar $calendar, bool $availableOnly, bool $excludeDrafts): Collection
    {
        $query = CalendarItem::query()
            ->where('calendar_id', $calendar->id);

        if ($excludeDrafts) {
            $query->where(function ($q): void {
                $q->whereNull('status')
                    ->orWhere('status', '!=', 'draft');
            });
        }

        if ($availableOnly) {
            $query->where('is_available', true)
                ->where(function ($q): void {
                    $q->whereNull('item_type')
                        ->orWhereNotIn('item_type', ['travel', 'practical_test']);
                })
                ->whereDoesntHave('lessons');

            // Exclude slots that overlap with any booked/reserved/completed item on the same calendar
            $bookedItems = CalendarItem::query()
                ->where('calendar_id', $calendar->id)
                ->whereNotNull('status')
                ->whereIn('status', ['draft', 'reserved', 'booked', 'completed'])
                ->get(['start_time', 'end_time']);

            if ($bookedItems->isNotEmpty()) {
                $query->where(function ($q) use ($bookedItems): void {
                    foreach ($bookedItems as $booked) {
                        $q->whereNot(function ($sub) use ($booked): void {
                            $sub->where('start_time', '<', $booked->end_time)
                                ->where('end_time', '>', $booked->start_time);
                        });
                    }
                });
            }
        }

        $query->orderBy('start_time');

        return $query->get();
    }
}
