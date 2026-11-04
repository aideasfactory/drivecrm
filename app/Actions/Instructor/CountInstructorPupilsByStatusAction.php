<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Models\Student;

class CountInstructorPupilsByStatusAction
{
    /**
     * Count an instructor's pupils per Summary bucket.
     *
     * Buckets mirror GetGroupedStudentsAction (used by the mobile app):
     * `active` is current, `passed` is passed, and every other status
     * (`inactive`, `on_hold`, `failed`, `completed`) is archived. There is no
     * waiting-list student status yet, so that bucket is always 0.
     *
     * @return array{current_pupils: int, passed_pupils: int, archived_pupils: int, waiting_list: int}
     */
    public function __invoke(Instructor $instructor): array
    {
        $countsByStatus = Student::query()
            ->where('instructor_id', $instructor->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $current = $countsByStatus->get('active', 0);
        $passed = $countsByStatus->get('passed', 0);

        return [
            'current_pupils' => $current,
            'passed_pupils' => $passed,
            'archived_pupils' => $countsByStatus->sum() - $current - $passed,
            'waiting_list' => 0,
        ];
    }
}
