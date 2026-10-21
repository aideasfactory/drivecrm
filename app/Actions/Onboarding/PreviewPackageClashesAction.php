<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Calendar\DescribeBlockingCalendarItemAction;
use App\Actions\Calendar\FindBlockingCalendarItemsAction;
use App\Models\Calendar;
use App\Models\CalendarItem;
use Carbon\Carbon;

class PreviewPackageClashesAction
{
    public function __construct(
        protected FindBlockingCalendarItemsAction $findBlockingCalendarItems,
        protected DescribeBlockingCalendarItemAction $describeBlockingCalendarItem,
    ) {}

    /**
     * Work out every weekly lesson date a package starting in this slot would
     * get, and what (if anything) each one clashes with. Read-only: nothing is
     * held. Uses the same clash rules as the booking itself, so a week flagged
     * here is booked as provisional.
     *
     * @return list<array{lesson_number: int, date: string, start_time: string, end_time: string, clashes: list<string>}>
     */
    public function __invoke(CalendarItem $firstSlot, int $lessonsCount): array
    {
        $firstSlot->loadMissing('calendar');

        $instructorId = $firstSlot->calendar->instructor_id;
        $firstDate = $firstSlot->calendar->date->copy();
        $startTime = Carbon::parse($firstSlot->start_time)->format('H:i');
        $endTime = Carbon::parse($firstSlot->end_time)->format('H:i');

        $calendars = Calendar::query()
            ->where('instructor_id', $instructorId)
            ->whereIn('date', collect(range(0, max($lessonsCount - 1, 0)))
                ->map(fn (int $week): string => $firstDate->copy()->addWeeks($week)->toDateString()))
            ->get()
            ->keyBy(fn (Calendar $calendar): string => $calendar->date->toDateString());

        $weeks = [];

        for ($week = 0; $week < $lessonsCount; $week++) {
            $date = $firstDate->copy()->addWeeks($week)->toDateString();
            $calendar = $calendars->get($date);

            $clashes = $calendar
                ? ($this->findBlockingCalendarItems)
                    ->forTimeRange($calendar->id, $startTime, $endTime, $week === 0 ? $firstSlot->id : null)
                    ->map(fn (CalendarItem $item): string => ($this->describeBlockingCalendarItem)($item))
                    ->values()
                    ->all()
                : [];

            $weeks[] = [
                'lesson_number' => $week + 1,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'clashes' => $clashes,
            ];
        }

        return $weeks;
    }
}
