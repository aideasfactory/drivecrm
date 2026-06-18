<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Enums\LessonStatus;
use App\Enums\ReminderType;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetMilesStartRemindersDueAction
{
    /**
     * Find each instructor's first PENDING lesson of today that is due a
     * "miles start" reminder: now is within [start − 30m, start) and no
     * miles_start reminder row exists yet.
     *
     * The "first lesson" is determined across ALL of today's pending lessons
     * (not just un-reminded ones) so that once the genuine first lesson has been
     * reminded, a later lesson is never mistaken for the first.
     *
     * @return Collection<int, Lesson>
     */
    public function __invoke(): Collection
    {
        $now = CarbonImmutable::now();

        $firstLessons = Lesson::query()
            ->whereDate('date', $now->toDateString())
            ->where('status', LessonStatus::PENDING->value)
            ->whereNotNull('instructor_id')
            ->whereNotNull('start_time')
            ->with('instructor.user')
            ->orderBy('start_time')
            ->get()
            ->groupBy('instructor_id')
            ->map(fn (Collection $lessons): Lesson => $lessons->first());

        return $firstLessons
            ->filter(function (Lesson $lesson) use ($now): bool {
                $start = CarbonImmutable::parse(
                    $lesson->date->toDateString().' '.$lesson->start_time->format('H:i')
                );

                if ($now < $start->subMinutes(30) || $now >= $start) {
                    return false;
                }

                return ! $lesson->reminders()
                    ->where('type', ReminderType::MILES_START->value)
                    ->exists();
            })
            ->values();
    }
}
