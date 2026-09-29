<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Illuminate\Database\Eloquent\Collection;

class GetInstructorCalendarItemsInRangeAction
{
    public function __construct(
        protected GetInstructorCalendarItemsAction $getCalendarItems
    ) {}

    /**
     * Get calendar items for an instructor between two dates (inclusive).
     *
     * Applies the same per-day filtering as the single-day action (including the
     * per-calendar overlap check when `$availableOnly` is true) and returns the
     * items ordered by date, then start time, with `calendar` already set.
     *
     * @return Collection<int, CalendarItem>
     */
    public function __invoke(Instructor $instructor, string $from, string $to, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        $calendars = Calendar::query()
            ->where('instructor_id', $instructor->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();

        $items = new Collection;

        foreach ($calendars as $calendar) {
            $dayItems = $this->getCalendarItems->forCalendar($calendar, $availableOnly, $excludeDrafts)
                ->each(fn (CalendarItem $item) => $item->setRelation('calendar', $calendar));

            $items = $items->merge($dayItems);
        }

        return $items;
    }
}
