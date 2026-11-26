<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\CalendarItem;

class TakeOverOpenAvailabilityAction
{
    /**
     * Delete any open, unbooked slot (and its travel block) on the same day
     * that a lesson's diary item now overlaps, so the same time can't be
     * booked twice. Mirrors `RebookBankedLessonAction`.
     */
    public function __invoke(CalendarItem $lessonItem): void
    {
        if (! $lessonItem->start_time || ! $lessonItem->end_time) {
            return;
        }

        CalendarItem::query()
            ->where('calendar_id', $lessonItem->calendar_id)
            ->whereKeyNot($lessonItem->id)
            ->where(fn ($query) => $query->whereNull('parent_item_id')->orWhere('parent_item_id', '!=', $lessonItem->id))
            ->overlapping((string) $lessonItem->start_time, (string) $lessonItem->end_time)
            ->with('parentItem')
            ->get()
            ->map(fn (CalendarItem $item): ?CalendarItem => match (true) {
                $item->isTravel() && $item->parentItem?->isEmptyAvailability() => $item->parentItem,
                $item->isEmptyAvailability() => $item,
                default => null,
            })
            ->filter()
            ->unique('id')
            ->each(function (CalendarItem $slot): void {
                $slot->travelItem?->delete();
                $slot->delete();
            });
    }
}
