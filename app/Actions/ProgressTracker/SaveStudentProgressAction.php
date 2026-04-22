<?php

declare(strict_types=1);

namespace App\Actions\ProgressTracker;

use App\Models\ProgressSubcategory;
use App\Models\Student;
use App\Models\StudentProgress;
use Illuminate\Support\Facades\DB;

class SaveStudentProgressAction
{
    /**
     * Bulk-upsert a student's scores. Each entry is:
     *   ['progress_subcategory_id' => int, 'score' => int (1-5)]
     *
     * Subcategories not owned by the student's instructor (or that are
     * soft-deleted) are ignored — we don't want the app writing scores
     * against archived or foreign subcats.
     */
    public function __invoke(Student $student, array $scores): void
    {
        if (empty($scores) || $student->instructor_id === null) {
            return;
        }

        $submittedIds = array_column($scores, 'progress_subcategory_id');

        $validIds = ProgressSubcategory::query()
            ->whereIn('id', $submittedIds)
            ->whereHas('category', fn ($q) => $q->where('instructor_id', $student->instructor_id))
            ->pluck('id')
            ->flip();

        DB::transaction(function () use ($student, $scores, $validIds): void {
            foreach ($scores as $entry) {
                if (! isset($validIds[$entry['progress_subcategory_id']])) {
                    continue;
                }

                StudentProgress::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'progress_subcategory_id' => $entry['progress_subcategory_id'],
                    ],
                    ['score' => $entry['score']],
                );
            }
        });
    }
}
