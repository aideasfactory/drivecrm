<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\SlotOfferStatus;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\SlotOffer;
use Illuminate\Validation\ValidationException;

class CancelSlotOfferAction
{
    public function __invoke(Instructor $instructor, CalendarItem $calendarItem): SlotOffer
    {
        $calendarItem->loadMissing('calendar');

        if ($calendarItem->calendar->instructor_id !== $instructor->id) {
            throw ValidationException::withMessages([
                'calendar_item_id' => 'Calendar item not found for this instructor.',
            ]);
        }

        $offer = SlotOffer::query()
            ->where('calendar_item_id', $calendarItem->id)
            ->where('status', SlotOfferStatus::Open)
            ->first();

        if (! $offer) {
            throw ValidationException::withMessages([
                'calendar_item_id' => 'There is no open offer on this diary slot.',
            ]);
        }

        $offer->update([
            'status' => SlotOfferStatus::Cancelled,
        ]);

        return $offer->fresh(['package', 'calendarItem.calendar']);
    }
}
