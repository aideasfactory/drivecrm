<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Models\Instructor;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Collection;

class DetectLessonClashesAction
{
    /**
     * Find lessons in the destination instructor's diary that overlap with any of the
     * incoming lessons being transferred. Two lessons clash when they share a date and
     * their start/end time ranges overlap.
     *
     * @param  Collection<int, Lesson>  $incomingLessons
     * @return Collection<int, Lesson> Lessons currently in the destination's diary that clash
     */
    public function __invoke(Instructor $destination, Collection $incomingLessons): Collection
    {
        if ($incomingLessons->isEmpty()) {
            return new Collection;
        }

        $dates = $incomingLessons->pluck('date')->unique()->values();

        $existing = Lesson::query()
            ->where('instructor_id', $destination->id)
            ->whereIn('date', $dates)
            ->get();

        return $existing->filter(function (Lesson $existingLesson) use ($incomingLessons) {
            return $incomingLessons->contains(function (Lesson $incoming) use ($existingLesson) {
                if (! $incoming->date->isSameDay($existingLesson->date)) {
                    return false;
                }

                $incomingStart = $incoming->start_time;
                $incomingEnd = $incoming->end_time;
                $existingStart = $existingLesson->start_time;
                $existingEnd = $existingLesson->end_time;

                if (! $incomingStart || ! $incomingEnd || ! $existingStart || ! $existingEnd) {
                    return false;
                }

                return $incomingStart->lt($existingEnd) && $existingStart->lt($incomingEnd);
            });
        })->values();
    }
}
