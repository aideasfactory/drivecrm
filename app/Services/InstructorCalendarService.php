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
     * Get available calendar items for an instructor on a specific date.
     */
    public function getCalendarItems(Instructor $instructor, string $date): Collection
    {
        $key = $this->cacheKey('instructor', $instructor->id, "calendar:{$date}");

        return $this->remember($key, fn () => ($this->getCalendarItems)($instructor, $date));
    }
}
