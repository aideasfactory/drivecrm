<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Student\Checklist\GetStudentChecklistAction;
use App\Actions\Student\Checklist\UpdateStudentChecklistItemAction;
use App\Models\Student;
use App\Models\StudentChecklistItem;
use Illuminate\Database\Eloquent\Collection;

class StudentChecklistService extends BaseService
{
    public function __construct(
        protected GetStudentChecklistAction $getStudentChecklist,
        protected UpdateStudentChecklistItemAction $updateStudentChecklistItem
    ) {}

    /**
     * Get all checklist items for a student.
     *
     * @return Collection<int, StudentChecklistItem>
     */
    public function getChecklist(Student $student): Collection
    {
        return ($this->getStudentChecklist)($student);
    }

    /**
     * Update a single checklist item.
     *
     * @param  array{is_checked?: bool, date?: string|null, notes?: string|null}  $data
     */
    public function updateChecklistItem(StudentChecklistItem $checklistItem, array $data): StudentChecklistItem
    {
        return ($this->updateStudentChecklistItem)($checklistItem, $data);
    }
}
