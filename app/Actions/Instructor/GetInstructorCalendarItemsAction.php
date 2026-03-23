<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorCalendarItemsAction
{
    /**
     * Get all calendar items for an instructor on a specific date.
     * Returns available slots (excluding travel and practical test items).
     */
    public function __invoke(Instructor $instructor, string $date): Collection
    {
        $calendar = Calendar::query()
            ->where('instructor_id', $instructor->id)
            ->where('date', $date)
            ->with(['items' => function ($query): void {
                $query->where('is_available', true)
                    ->where(function ($q): void {
                        $q->whereNull('item_type')
                            ->orWhereNotIn('item_type', ['travel', 'practical_test']);
                    })
                    ->orderBy('start_time');
            }])
            ->first();

        if (! $calendar) {
            return collect();
        }

        return $calendar->items;
    }
}
