<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class GetStudentChecklistAction
{
    public function __construct(
        protected EnsureStudentChecklistAction $ensureStudentChecklist
    ) {}

    /**
     * Get all checklist items for a student, lazy-seeding defaults if none exist.
     */
    public function __invoke(Student $student): Collection
    {
        ($this->ensureStudentChecklist)($student);

        return $student->checklistItems()
            ->orderBy('sort_order')
            ->get();
    }
}
