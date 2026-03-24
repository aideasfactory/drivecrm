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
     * @param  bool  $excludeDrafts  When true, excludes items with draft status.
     */
    public function getCalendarItems(Instructor $instructor, string $date, bool $availableOnly = true, bool $excludeDrafts = true): Collection
    {
        $suffixParts = ["calendar:{$date}"];
        if (! $availableOnly) {
            $suffixParts[] = 'all';
        }
        if (! $excludeDrafts) {
            $suffixParts[] = 'drafts';
        }
        $suffix = implode(':', $suffixParts);
        $key = $this->cacheKey('instructor', $instructor->id, $suffix);

        return $this->remember($key, fn () => ($this->getCalendarItems)($instructor, $date, $availableOnly, $excludeDrafts));
    }

    /**
     * Invalidate cached calendar items for a specific instructor and date.
     *
     * Should be called whenever calendar items are booked, reserved, or otherwise modified.
     */
    public function invalidateCalendarCache(int $instructorId, string $date): void
    {
        $this->invalidate([
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}"),
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}:all"),
            $this->cacheKey('instructor', $instructorId, "calendar:{$date}:all:drafts"),
        ]);
    }
}
