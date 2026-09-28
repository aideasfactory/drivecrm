<?php

declare(strict_types=1);

namespace App\Actions\CalendarItem;

use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;

class ReleaseDraftCalendarItemsAction
{
    /**
     * Put draft calendar items back on offer and clear their draft travel blocks.
     * Items that are no longer drafts are left untouched.
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

        CalendarItem::query()
            ->whereIn('parent_item_id', $ids)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update(['status' => null]);

        return CalendarItem::query()
            ->whereIn('id', $ids)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update([
                'is_available' => true,
                'status' => null,
            ]);
    }
}
