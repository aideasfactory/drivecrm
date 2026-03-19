<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\StudentChecklistItem;

class UpdateStudentChecklistItemAction
{
    /**
     * Update a student checklist item with the given data.
     *
     * @param  array{is_checked?: bool, date?: string|null, notes?: string|null}  $data
     */
    public function __invoke(StudentChecklistItem $checklistItem, array $data): StudentChecklistItem
    {
        $checklistItem->update($data);

        return $checklistItem->refresh();
    }
}
