<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Calendar\RelinkOrphanedLessonsAction;

class DiaryRepairService extends BaseService
{
    public function __construct(
        protected RelinkOrphanedLessonsAction $relinkOrphanedLessons,
        protected InstructorCalendarService $instructorCalendarService,
    ) {}

    /**
     * Give booked lessons that lost their diary slot a slot again
     * (diary:relink-orphaned-lessons). Only finds what is still wrong, so a
     * second run changes nothing.
     *
     * @param  array<int, int>  $onlyLessonIds
     * @return array{counts: array<string, int>, lessons: array<int, array<string, mixed>>}
     */
    public function relinkOrphanedLessons(bool $dryRun, array $onlyLessonIds = []): array
    {
        $result = ($this->relinkOrphanedLessons)($dryRun, $onlyLessonIds);

        if (! $dryRun) {
            foreach ($result['dates_by_instructor'] as $instructorId => $dates) {
                foreach (array_keys($dates) as $date) {
                    $this->instructorCalendarService->invalidateCalendarCache($instructorId, $date);
                }
            }
        }

        return ['counts' => $result['counts'], 'lessons' => $result['lessons']];
    }
}
