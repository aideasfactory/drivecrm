<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorDayLessonsAction
{
    public function __construct(
        protected GetInstructorLessonsInRangeAction $getLessonsInRange
    ) {}

    /**
     * Fetch all lessons for an instructor on a specific date.
     *
     * A single-day range, so the day and week views always return the same
     * lesson set for a given date.
     */
    public function __invoke(Instructor $instructor, string $date): Collection
    {
        return ($this->getLessonsInRange)($instructor, $date, $date);
    }
}
