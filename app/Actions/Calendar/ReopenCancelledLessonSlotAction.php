<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\SlotOfferStatus;
use App\Models\CalendarItem;
use App\Models\SlotOffer;
use RuntimeException;

class ReopenCancelledLessonSlotAction
{
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
     * @throws RuntimeException If a lesson is still attached to the slot
     */
    public function __invoke(CalendarItem $calendarItem): CalendarItem
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
}
