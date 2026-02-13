<?php

namespace App\Services;

use App\Models\Calendar;
use Carbon\CarbonInterface;

class CalendarService
{
    /**
     * Get available dates and time slots for an instructor
     */
    public function getAvailability(
        string $instructorId,
        CarbonInterface $fromDate,
        CarbonInterface $toDate
    ): array {
        // Ensure we're starting from at least 2 days ahead
        $minimumDate = now()->addDays(2)->startOfDay();
        if ($fromDate->lt($minimumDate)) {
            $fromDate = $minimumDate;
        }

        $calendars = Calendar::query()
            ->where('instructor_id', $instructorId)
            ->whereBetween('date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')])
            ->where('date', '>=', $minimumDate->format('Y-m-d')) // Extra safety check
            ->with(['items' => function ($query) {
                $query->where('is_available', true)
                    ->orderBy('start_time');
            }])
            ->orderBy('date')
            ->get();

        $availability = [];
        $firstAvailableIndex = null;
        $index = 0;

        foreach ($calendars as $calendar) {
            $slots = $calendar->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'start_time' => \Illuminate\Support\Carbon::parse($item->start_time)->format('H:i'),
                    'end_time' => \Illuminate\Support\Carbon::parse($item->end_time)->format('H:i'),
                ];
            });

            if ($slots->isNotEmpty() && $firstAvailableIndex === null) {
                $firstAvailableIndex = $index;
            }

            $availability[] = [
                'date' => $calendar->date->format('Y-m-d'),
                'slots' => $slots,
                'has_availability' => $slots->isNotEmpty(),
            ];

            $index++;
        }

        return [
            'dates' => $availability,
            'default_selected_index' => $firstAvailableIndex !== null
                ? min($firstAvailableIndex + 2, count($availability) - 1) // Third available date
                : null,
        ];
    }
}
