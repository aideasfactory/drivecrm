<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\CalendarItemType;
use App\Models\CalendarItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateTravelTimeAction
{
    /**
     * Set or remove the travel time after a diary item, whatever its status
     * (empty slot or booked lesson). Accepts the slot or its travel block;
     * 0 minutes removes the travel block. Lessons, payments and the pupil
     * are not touched.
     *
     * @param  CalendarItem  $calendarItem  The slot / lesson, or its travel block
     * @param  int  $minutes  0, 15, 30, 45 or 60
     */
    public function __invoke(CalendarItem $calendarItem, int $minutes): void
    {
        $slot = $calendarItem->isTravel() ? $calendarItem->parentItem : $calendarItem;

        if ($slot === null) {
            $this->updateDetachedTravelBlock($calendarItem, $minutes);

            return;
        }

        DB::transaction(function () use ($slot, $minutes): void {
            $slot->update(['travel_time_minutes' => $minutes > 0 ? $minutes : null]);

            $travelItem = $slot->travelItem;

            if ($minutes === 0) {
                $travelItem?->delete();

                return;
            }

            $window = self::travelWindow($slot, $minutes);

            if ($travelItem !== null) {
                $travelItem->update([
                    'calendar_id' => $slot->calendar_id,
                    'start_time' => $window['start_time'],
                    'end_time' => $window['end_time'],
                ]);

                return;
            }

            CalendarItem::create([
                'calendar_id' => $slot->calendar_id,
                'start_time' => $window['start_time'],
                'end_time' => $window['end_time'],
                'is_available' => false,
                'item_type' => CalendarItemType::Travel,
                'parent_item_id' => $slot->id,
                'status' => null,
                'notes' => null,
                'unavailability_reason' => 'Travel time',
            ]);
        });
    }

    /**
     * The time the travel block would occupy: straight after the slot, or from
     * its own start for a travel block with no slot.
     *
     * @return array{start_time: string, end_time: string}
     */
    public static function travelWindow(CalendarItem $calendarItem, int $minutes): array
    {
        $start = Carbon::parse($calendarItem->isTravel() ? $calendarItem->start_time : $calendarItem->end_time);

        return [
            'start_time' => $start->format('H:i:s'),
            'end_time' => $start->copy()->addMinutes($minutes)->format('H:i:s'),
        ];
    }

    /**
     * A travel block whose slot has gone is resized from its own start, or removed.
     */
    private function updateDetachedTravelBlock(CalendarItem $travelItem, int $minutes): void
    {
        if ($minutes === 0) {
            $travelItem->delete();

            return;
        }

        $travelItem->update(['end_time' => self::travelWindow($travelItem, $minutes)['end_time']]);
    }
}
