<?php

declare(strict_types=1);

namespace App\Actions\CalendarItem;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Models\CalendarItem;
use App\Models\Lesson;
use Illuminate\Support\Facades\Log;

class ResetDraftCalendarItemsAction
{
    /**
     * Reset all draft calendar items created before the given cutoff back to available.
     * Also deletes any draft lessons linked to those calendar items.
     *
     * @return int The number of calendar items reset
     */
    public function __invoke(\DateTimeInterface $cutoff): int
    {
        $draftCalendarItemIds = CalendarItem::query()
            ->where('status', CalendarItemStatus::DRAFT)
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($draftCalendarItemIds->isEmpty()) {
            return 0;
        }

        // Delete draft lessons linked to these calendar items
        $lessonsDeleted = Lesson::query()
            ->whereIn('calendar_item_id', $draftCalendarItemIds)
            ->where('status', LessonStatus::DRAFT)
            ->delete();

        if ($lessonsDeleted > 0) {
            Log::info('ResetDraftCalendarItems: Deleted draft lessons', [
                'lessons_deleted' => $lessonsDeleted,
                'calendar_item_ids' => $draftCalendarItemIds->toArray(),
            ]);
        }

        // Reset travel items of these draft calendar items back to null status
        CalendarItem::query()
            ->whereIn('parent_item_id', $draftCalendarItemIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->update([
                'status' => null,
            ]);

        // Reset the calendar items back to available
        $updated = CalendarItem::query()
            ->whereIn('id', $draftCalendarItemIds)
            ->update([
                'is_available' => true,
                'status' => null,
            ]);

        return $updated;
    }
}
