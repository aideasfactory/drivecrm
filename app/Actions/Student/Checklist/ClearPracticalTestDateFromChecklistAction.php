<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\Student;
use App\Models\StudentChecklistItem;

class ClearPracticalTestDateFromChecklistAction
{
    /**
     * Clear the practical-test date from the student's "book_practical_test"
     * checklist item when a booked practical test is removed.
     *
     * Nulls the date and unchecks the item — the inverse of
     * SyncPracticalTestDateToChecklistAction. A no-op (returns null) when the
     * student has no such checklist item.
     */
    public function __invoke(Student $student): ?StudentChecklistItem
    {
        $checklistItem = $student->checklistItems()
            ->where('key', 'book_practical_test')
            ->first();

        if (! $checklistItem) {
            return null;
        }

        $checklistItem->update([
            'date' => null,
            'is_checked' => false,
        ]);

        return $checklistItem->refresh();
    }
}
