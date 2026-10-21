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
        return $this->forTimeRange(
            $calendarItem->calendar_id,
            (string) $calendarItem->start_time,
            (string) $calendarItem->end_time,
            $calendarItem->id,
        );
    }

    /**
     * Find what a booking at this time on this diary day would clash with,
     * ignoring an item (and its travel block) that is being booked or checked.
     *
     * @return Collection<int, CalendarItem>
     */
    public function forTimeRange(int $calendarId, string $startTime, string $endTime, ?int $excludeItemId = null): Collection
    {
        return CalendarItem::query()
            ->where('calendar_id', $calendarId)
            ->when($excludeItemId, fn (Builder $query) => $query
                ->whereKeyNot($excludeItemId)
                ->where(fn (Builder $query) => $query
                    ->whereNull('parent_item_id')
                    ->orWhere('parent_item_id', '!=', $excludeItemId)))
            ->overlapping($startTime, $endTime)
            ->blocksBooking()
            ->with(['lessons.order.student', 'parentItem.lessons.order.student', 'student'])
            ->orderBy('start_time')
            ->get();
    }
}
