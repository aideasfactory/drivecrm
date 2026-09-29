<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\CalendarItem\ReleaseDraftCalendarItemsAction;
use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;
use App\Models\Enquiry;
use App\Services\InstructorCalendarService;
use Illuminate\Support\Facades\Log;

class ReleaseLegacyStepFourHoldsAction
{
    public function __construct(
        protected ReleaseDraftCalendarItemsAction $releaseDraftCalendarItems,
        protected InstructorCalendarService $instructorCalendarService,
    ) {}

    /**
     * Step 4 used to hold the chosen slots in the diary straight away (stored as
     * `calendar_item_ids`). It no longer does, so put any such holds for this
     * enquiry back on offer. Only drafts with no lessons are touched — once an
     * order exists its holds are managed by the order.
     */
    public function __invoke(Enquiry $enquiry): int
    {
        $step4 = $enquiry->getStepData(4) ?? [];
        $legacyIds = $step4['calendar_item_ids'] ?? [];

        if (empty($legacyIds)) {
            return 0;
        }

        $releasableIds = CalendarItem::query()
            ->whereIn('id', $legacyIds)
            ->where('status', CalendarItemStatus::DRAFT)
            ->whereDoesntHave('lessons')
            ->pluck('id');

        $released = ($this->releaseDraftCalendarItems)($releasableIds);

        unset($step4['calendar_item_ids']);
        $enquiry->setStepData(4, $step4);
        $enquiry->save();

        if ($released > 0) {
            $this->invalidateCalendarCache($releasableIds->all());

            Log::info('Released legacy step 4 holds', [
                'enquiry_id' => $enquiry->id,
                'calendar_item_ids' => $releasableIds->all(),
            ]);
        }

        return $released;
    }

    /**
     * @param  array<int, int>  $calendarItemIds
     */
    protected function invalidateCalendarCache(array $calendarItemIds): void
    {
        CalendarItem::whereIn('calendar_items.id', $calendarItemIds)
            ->join('calendars', 'calendar_items.calendar_id', '=', 'calendars.id')
            ->get(['calendars.instructor_id', 'calendars.date'])
            ->unique(fn ($row) => $row->instructor_id.'|'.$row->date)
            ->each(fn ($row) => $this->instructorCalendarService->invalidateCalendarCache((int) $row->instructor_id, (string) $row->date));
    }
}
