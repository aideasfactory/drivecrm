<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\SlotOfferStatus;
use App\Models\SlotOffer;

class CloseOpenSlotOffersForItemsAction
{
    /**
     * Cancel any open short-notice offers on the given calendar items.
     *
     * @param  array<int, int>  $calendarItemIds
     */
    public function __invoke(array $calendarItemIds, ?int $exceptOfferId = null): void
    {
        if ($calendarItemIds === []) {
            return;
        }

        SlotOffer::query()
            ->whereIn('calendar_item_id', $calendarItemIds)
            ->where('status', SlotOfferStatus::Open)
            ->when($exceptOfferId !== null, fn ($query) => $query->where('id', '!=', $exceptOfferId))
            ->update(['status' => SlotOfferStatus::Cancelled->value]);
    }
}
