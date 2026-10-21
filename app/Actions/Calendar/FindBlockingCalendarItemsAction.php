<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\CalendarItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FindBlockingCalendarItemsAction
{
    /**
     * Find what a booked diary item clashes with on its day: another lesson
     * (held, reserved, booked or completed), a blocked-out period or a
     * practical test. The item itself and its own travel block are ignored.
     *
     * Each result has its lessons (with order and student) and its parent
     * item's lessons loaded, so callers can name the pupil behind it.
     *
     * @return Collection<int, CalendarItem>
     */
    public function __invoke(CalendarItem $calendarItem): Collection
    {
        return CalendarItem::query()
            ->where('calendar_id', $calendarItem->calendar_id)
            ->whereKeyNot($calendarItem->id)
            ->where(fn (Builder $query) => $query
                ->whereNull('parent_item_id')
                ->orWhere('parent_item_id', '!=', $calendarItem->id))
            ->overlapping((string) $calendarItem->start_time, (string) $calendarItem->end_time)
            ->blocksBooking()
            ->with(['lessons.order.student', 'parentItem.lessons.order.student', 'student'])
            ->orderBy('start_time')
            ->get();
    }
}
