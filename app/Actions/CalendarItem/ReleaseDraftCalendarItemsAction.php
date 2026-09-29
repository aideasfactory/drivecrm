<?php

declare(strict_types=1);

namespace App\Actions\CalendarItem;

use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;

class ReleaseDraftCalendarItemsAction
{
    /**
     * Release the diary slots held by an unpaid booking. Slots the hold created
     * (where the instructor had no availability) are deleted along with their
     * travel blocks. Slots that were availability before the hold go back on
     * offer. Items created before `created_by_hold` existed (null) are treated
     * as availability, as before. Items that are no longer drafts are left
     * untouched. The draft lessons must already have been deleted.
     *
     * @param  iterable<int, int>  $calendarItemIds
     * @return int The number of calendar items released
     */
    public function __invoke(iterable $calendarItemIds): int
    {
        $ids = collect($calendarItemIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $createdByHoldIds = CalendarItem::query()
            ->whereIn('id', $ids)
            ->where('status', CalendarItemStatus::DRAFT)
            ->where('created_by_hold', true)
            ->pluck('id');

        $deleted = 0;

        if ($createdByHoldIds->isNotEmpty()) {
            CalendarItem::query()
                ->whereIn('parent_item_id', $createdByHoldIds)
                ->delete();

            $deleted = CalendarItem::query()
                ->whereIn('id', $createdByHoldIds)
                ->delete();
        }

        $restoreIds = $ids->diff($createdByHoldIds);

        if ($restoreIds->isEmpty()) {
            return $deleted;
        }

        CalendarItem::query()
            ->whereIn('parent_item_id', $restoreIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update(['status' => null]);

        $restored = CalendarItem::query()
            ->whereIn('id', $restoreIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update([
                'is_available' => true,
                'status' => null,
                'created_by_hold' => null,
            ]);

        return $deleted + $restored;
    }
}
