<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\SlotOfferStatus;
use App\Models\CalendarItem;
use App\Models\SlotOffer;
use RuntimeException;

class ReopenCancelledLessonSlotAction
{
    /**
     * Shortest slot that may be reopened for an old-system (imported) lesson.
     * Platform lessons are 2 hours; a shorter gap could be booked as 2 hours
     * and run into time the instructor never added to their diary.
     */
    public const MIN_REOPEN_MINUTES_FOR_IMPORTED = 120;

    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * Turn a cancelled lesson's diary slot back into open availability so the
     * instructor can offer it to other pupils. Its travel block stays attached.
     * A slot offer that was booked into this slot is closed so the slot can be
     * offered again. The cancelled lesson must already be detached.
     *
     * A cancelled paid driving test is never offered as availability: its
     * practical test entry stays in the diary as a plain (unpaid) test for the
     * instructor to keep or delete.
     *
     * A slot freed by an imported (old-system) lesson that is shorter than
     * 2 hours is removed quietly with its travel block instead of reopened
     * (agreed with Head Office 2026-11-25). Returns null when removed.
     *
     * @throws RuntimeException If a lesson is still attached to the slot
     */
    public function __invoke(CalendarItem $calendarItem, bool $wasImportedLesson = false): ?CalendarItem
    {
        if ($calendarItem->lessons()->exists()) {
            throw new RuntimeException('Cannot reopen a diary slot that still has a lesson attached.');
        }

        if ($calendarItem->isPracticalTest()) {
            $calendarItem->is_available = false;
            $calendarItem->status = null;
            $calendarItem->save();

            return $calendarItem;
        }

        if ($wasImportedLesson && $this->isShorterThanALesson($calendarItem)) {
            ($this->deleteCalendarItem)($calendarItem);

            return null;
        }

        CalendarItem::query()
            ->where('parent_item_id', $calendarItem->id)
            ->update(['status' => null]);

        SlotOffer::query()
            ->where('calendar_item_id', $calendarItem->id)
            ->where('status', SlotOfferStatus::Booked)
            ->update(['status' => SlotOfferStatus::Cancelled->value]);

        $calendarItem->is_available = true;
        $calendarItem->status = null;
        $calendarItem->created_by_hold = null;
        $calendarItem->save();

        return $calendarItem;
    }

    private function isShorterThanALesson(CalendarItem $calendarItem): bool
    {
        if (! $calendarItem->start_time || ! $calendarItem->end_time) {
            return false;
        }

        $minutes = (strtotime($calendarItem->end_time) - strtotime($calendarItem->start_time)) / 60;

        return $minutes < self::MIN_REOPEN_MINUTES_FOR_IMPORTED;
    }
}
