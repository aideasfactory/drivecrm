<?php

declare(strict_types=1);

namespace App\Actions\CalendarItem;

use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;

class ResetDraftCalendarItemsAction
{
    /**
     * Reset all draft calendar items created before the given cutoff back to available.
     *
     * @return int The number of items reset
     */
    public function __invoke(\DateTimeInterface $cutoff): int
    {
        return CalendarItem::query()
            ->where('status', CalendarItemStatus::DRAFT)
            ->where('created_at', '<', $cutoff)
            ->update([
                'is_available' => true,
                'status' => null,
            ]);
    }
}
