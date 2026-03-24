<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Illuminate\Support\Collection;

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
     */
    public function __invoke(Instructor $instructor, string $date, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        $calendar = Calendar::query()
            ->where('instructor_id', $instructor->id)
            ->where('date', $date)
            ->first();

        if (! $calendar) {
            return collect();
        }

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
