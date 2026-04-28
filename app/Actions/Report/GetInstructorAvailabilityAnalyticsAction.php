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
     * Utilisation is computed as booked / (booked + free) × 100, where:
     *   - booked = calendar items with status BOOKED or COMPLETED
     *   - free   = calendar items with is_available=true (booked items have is_available=false in production)
     * Lunch / holiday / unavailability blocks (is_available=false, no booked status) are excluded from the denominator.
     *
     * @return array{instructors: Collection, summary: array{total_slots: int, total_booked: int, total_free: int, overall_utilization: float}}
     */
    public function __invoke(): array
    {
        $instructors = Instructor::query()
            ->active()
            ->with('user')
            ->get();

        $analytics = $instructors->map(function (Instructor $instructor) {
            $totalFree = $instructor->calendars()
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

            $totalSlots = $totalFree + $totalBooked;
            $utilizationRate = $totalSlots > 0
                ? round(($totalBooked / $totalSlots) * 100, 1)
                : 0.0;

            return [
                'id' => $instructor->id,
                'name' => $instructor->name,
                'avatar' => $instructor->avatar,
                'total_slots' => $totalSlots,
                'total_booked' => $totalBooked,
                'total_free' => $totalFree,
                'utilization_rate' => $utilizationRate,
            ];
        });

        $totalSlots = $analytics->sum('total_slots');
        $totalBooked = $analytics->sum('total_booked');

        return [
            'instructors' => $analytics->sortByDesc('utilization_rate')->values(),
            'summary' => [
                'total_slots' => $totalSlots,
                'total_booked' => $totalBooked,
                'total_free' => max(0, $totalSlots - $totalBooked),
                'overall_utilization' => $totalSlots > 0
                    ? round(($totalBooked / $totalSlots) * 100, 1)
                    : 0.0,
            ],
        ];
    }
}
