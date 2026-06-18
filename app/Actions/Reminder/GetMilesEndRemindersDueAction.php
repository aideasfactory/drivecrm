<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Enums\LessonStatus;
use App\Enums\ReminderType;
use App\Models\Lesson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetMilesEndRemindersDueAction
{
    /**
     * Find each instructor's last non-cancelled lesson of today that is due a
     * "miles end" reminder: now is at or after end + 30m, it is still that
     * lesson's calendar day, and no miles_end reminder row exists yet.
     *
     * Day boundary lapses — once midnight passes the lesson's date no longer
     * matches today, so the reminder is intentionally skipped rather than
     * back-filled.
     *
     * @return Collection<int, Lesson>
     */
    public function __invoke(): Collection
    {
        $now = CarbonImmutable::now();

        $lastLessons = Lesson::query()
            ->whereDate('date', $now->toDateString())
            ->where('status', '!=', LessonStatus::CANCELLED->value)
            ->whereNotNull('instructor_id')
            ->whereNotNull('end_time')
            ->with('instructor.user')
            ->orderBy('end_time')
            ->get()
            ->groupBy('instructor_id')
            ->map(fn (Collection $lessons): Lesson => $lessons->last());

        return $lastLessons
            ->filter(function (Lesson $lesson) use ($now): bool {
                $end = CarbonImmutable::parse(
                    $lesson->date->toDateString().' '.$lesson->end_time->format('H:i')
                );

                if ($now < $end->addMinutes(30)) {
                    return false;
                }

                return ! $lesson->reminders()
                    ->where('type', ReminderType::MILES_END->value)
                    ->exists();
            })
            ->values();
    }
}
