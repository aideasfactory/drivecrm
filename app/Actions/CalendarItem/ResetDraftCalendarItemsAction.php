<?php

declare(strict_types=1);

namespace App\Actions\CalendarItem;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\CalendarItem;
use App\Models\Lesson;
use Illuminate\Support\Facades\Log;

class ResetDraftCalendarItemsAction
{
    public function __construct(
        protected ReleaseDraftCalendarItemsAction $releaseDraftCalendarItems,
    ) {}

    /**
     * Reset all draft calendar items created before the given cutoff back to available.
     * Also deletes any draft lessons linked to those calendar items.
     *
     * Drafts belonging to an unpaid order with a payment hold are skipped — the
     * hold decides when they are released (see `orders:release-expired-holds`).
     *
     * @return int The number of calendar items reset
     */
    public function __invoke(\DateTimeInterface $cutoff): int
    {
        $draftCalendarItemIds = CalendarItem::query()
            ->where('status', CalendarItemStatus::DRAFT)
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('lessons.order', fn ($query) => $query
                ->where('status', OrderStatus::PENDING)
                ->whereNotNull('payment_hold_expires_at'))
            ->pluck('id');

        if ($draftCalendarItemIds->isEmpty()) {
            return 0;
        }

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

        return ($this->releaseDraftCalendarItems)($draftCalendarItemIds);
    }
}
