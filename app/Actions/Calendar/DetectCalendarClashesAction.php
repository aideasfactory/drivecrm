<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use Illuminate\Support\Collection;

class DetectCalendarClashesAction
{
    /**
     * Find existing calendar items that overlap with the given time range.
     *
     * Returns clashing CalendarItems with lessons and students eager-loaded.
     * Excludes travel blocks (they are managed separately).
     *
     * @param  int|null  $excludeItemId  Calendar item ID to exclude (for update scenarios)
     * @return Collection<int, CalendarItem>
     */
    public function __invoke(
        Instructor $instructor,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeItemId = null
    ): Collection {
        $calendar = $instructor->calendars()
            ->where('date', $date)
            ->first();

        if (! $calendar) {
            return collect();
        }

        return $calendar->items()
            ->where('item_type', '!=', 'travel')
            ->when($excludeItemId, fn ($query) => $query->where('id', '!=', $excludeItemId))
            ->where(function ($query) use ($startTime, $endTime): void {
                $query->whereRaw('TIME(?) < TIME(end_time)', [$startTime])
                    ->whereRaw('TIME(?) > TIME(start_time)', [$endTime]);
            })
            ->with(['lessons.order.student.user'])
            ->get();
    }
}
