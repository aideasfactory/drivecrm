<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Import\MergeDuplicateProgressFrameworkAction;
use App\Actions\Import\ResolveImportedLessonClashesAction;
use App\Actions\Import\RemoveSlotsUnderImportedLessonsAction;

class ImportRepairService extends BaseService
{
    public function __construct(
        protected ResolveImportedLessonClashesAction $resolveImportedLessonClashes,
        protected RemoveSlotsUnderImportedLessonsAction $removeSlotsUnderImportedLessons,
        protected MergeDuplicateProgressFrameworkAction $mergeDuplicateProgressFramework,
        protected InstructorCalendarService $instructorCalendarService,
    ) {}

    /**
     * Repair what the Go Roadie import (production run #33) left behind
     * (import:repair). Clashing imported lessons go first, so an open slot
     * that only clashed with a cancelled duplicate stays as availability. Every step
     * only finds what is still wrong, so a second run changes nothing.
     *
     * @return array{lessons: array<string, mixed>, slots: array<string, int>, progress: array<string, int>}
     */
    public function repair(bool $dryRun): array
    {
        $lessons = ($this->resolveImportedLessonClashes)($dryRun);
        $slots = ($this->removeSlotsUnderImportedLessons)($dryRun);
        $progress = ($this->mergeDuplicateProgressFramework)($dryRun);

        if (! $dryRun) {
            $this->clearCalendarCaches($lessons['dates_by_instructor']);
            $this->clearCalendarCaches($slots['dates_by_instructor']);
        }

        return [
            'lessons' => ['counts' => $lessons['counts'], 'lessons' => $lessons['lessons']],
            'slots' => $slots['counts'],
            'progress' => $progress,
        ];
    }

    /**
     * @param  array<int, array<string, true>>  $datesByInstructor
     */
    protected function clearCalendarCaches(array $datesByInstructor): void
    {
        foreach ($datesByInstructor as $instructorId => $dates) {
            foreach (array_keys($dates) as $date) {
                $this->instructorCalendarService->invalidateCalendarCache($instructorId, $date);
            }

            $this->invalidate($this->cacheKey('instructor', $instructorId, 'grouped_students'));
        }
    }
}
