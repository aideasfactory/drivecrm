<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GetInstructorCalendarAction
{
    /**
     * Get instructor's calendar with all calendar items for specified date range.
     *
     * @param  Instructor  $instructor  The instructor
     * @param  Carbon|null  $startDate  Start date (defaults to current week start)
     * @param  Carbon|null  $endDate  End date (defaults to current week end)
     * @return Collection Collection of calendar dates with their items
     */
    public function __invoke(
        Instructor $instructor,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        // Default to current week if no dates provided
        $startDate = $startDate ?? Carbon::now()->startOfWeek();
        $endDate = $endDate ?? Carbon::now()->endOfWeek();

        // Get all calendars for instructor within date range with their items
        $calendars = $instructor->calendars()
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['items' => function ($query) {
                $query->orderBy('start_time');
            }])
            ->orderBy('date')
            ->get();

        // Format the response for frontend consumption
        return $calendars->map(function ($calendar) {
            return [
                'id' => $calendar->id,
                'date' => $calendar->date->format('Y-m-d'),
                'items' => $calendar->items->map(function ($item) use ($calendar) {
                    return [
                        'id' => $item->id,
                        'calendar_id' => $calendar->id,
                        'date' => $calendar->date->format('Y-m-d'),
                        'start_time' => $item->start_time,
                        'end_time' => $item->end_time,
                        'is_available' => $item->is_available,
                        'status' => $item->status ?? 'available',
                    ];
                }),
            ];
        });
    }
}
