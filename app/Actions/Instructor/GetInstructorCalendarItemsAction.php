<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Calendar;
use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorCalendarItemsAction
{
    /**
     * Get calendar items for an instructor on a specific date.
     *
     * @param  bool  $availableOnly  When true, returns only available slots (excluding travel/practical test). When false, returns all items.
     */
    public function __invoke(Instructor $instructor, string $date, bool $availableOnly = true): Collection
    {
        $calendar = Calendar::query()
            ->where('instructor_id', $instructor->id)
            ->where('date', $date)
            ->with(['items' => function ($query) use ($availableOnly): void {
                if ($availableOnly) {
                    $query->where('is_available', true)
                        ->where(function ($q): void {
                            $q->whereNull('item_type')
                                ->orWhereNotIn('item_type', ['travel', 'practical_test']);
                        });
                }

                $query->orderBy('start_time');
            }])
            ->first();

        if (! $calendar) {
            return collect();
        }

        return $calendar->items;
    }
}
