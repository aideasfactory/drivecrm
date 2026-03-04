<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Enums\CalendarItemStatus;
use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorAvailabilityAnalyticsAction
{
    /**
     * Get availability and booking analytics for all active instructors.
     *
     * @return array{instructors: Collection, summary: array{total_available: int, total_booked: int, total_free: int, overall_utilization: float}}
     */
    public function __invoke(): array
    {
        $instructors = Instructor::query()
            ->active()
            ->with('user')
            ->get();

        $analytics = $instructors->map(function (Instructor $instructor) {
            $totalAvailable = $instructor->calendars()
                ->join('calendar_items', 'calendars.id', '=', 'calendar_items.calendar_id')
                ->where('calendar_items.is_available', true)
                ->count();

            $totalBooked = $instructor->calendars()
                ->join('calendar_items', 'calendars.id', '=', 'calendar_items.calendar_id')
                ->whereIn('calendar_items.status', [
                    CalendarItemStatus::BOOKED->value,
                    CalendarItemStatus::COMPLETED->value,
                ])
                ->count();

            $totalFree = max(0, $totalAvailable - $totalBooked);
            $utilizationRate = $totalAvailable > 0
                ? round(($totalBooked / $totalAvailable) * 100, 1)
                : 0.0;

            return [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'avatar' => $instructor->avatar,
                'total_available' => $totalAvailable,
                'total_booked' => $totalBooked,
                'total_free' => $totalFree,
                'utilization_rate' => $utilizationRate,
            ];
        });

        $totalAvailable = $analytics->sum('total_available');
        $totalBooked = $analytics->sum('total_booked');

        return [
            'instructors' => $analytics->sortByDesc('utilization_rate')->values(),
            'summary' => [
                'total_available' => $totalAvailable,
                'total_booked' => $totalBooked,
                'total_free' => max(0, $totalAvailable - $totalBooked),
                'overall_utilization' => $totalAvailable > 0
                    ? round(($totalBooked / $totalAvailable) * 100, 1)
                    : 0.0,
            ],
        ];
    }
}
