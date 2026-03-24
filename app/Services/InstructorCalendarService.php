<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Instructor\GetInstructorCalendarItemsAction;
use App\Models\Instructor;
use Illuminate\Support\Collection;

class InstructorCalendarService extends BaseService
{
    public function __construct(
        protected GetInstructorCalendarItemsAction $getCalendarItems
    ) {}

    /**
     * Get calendar items for an instructor on a specific date.
     *
     * @param  bool  $availableOnly  When true, returns only available slots. When false, returns all items for the day.
     */
    public function getCalendarItems(Instructor $instructor, string $date, bool $availableOnly = true): Collection
    {
        $suffix = $availableOnly ? "calendar:{$date}" : "calendar:{$date}:all";
        $key = $this->cacheKey('instructor', $instructor->id, $suffix);

        return $this->remember($key, fn () => ($this->getCalendarItems)($instructor, $date, $availableOnly));
    }
}
