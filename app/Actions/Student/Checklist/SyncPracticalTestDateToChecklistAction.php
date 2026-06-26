<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\Student;
use App\Models\StudentChecklistItem;

class SyncPracticalTestDateToChecklistAction
{
    /**
     * Carry a booked practical-test date over to the student's
     * "book_practical_test" checklist item.
     *
     * Sets the checklist item's date to the test date and marks it checked,
     * since booking the practical test completes the "Book practical test" task.
     * A no-op (returns null) when the student has no such checklist item.
     *
     * @param  string  $date  Practical test date in Y-m-d format
     */
    public function __invoke(Student $student, string $date): ?StudentChecklistItem
    {
        $checklistItem = $student->checklistItems()
            ->where('key', 'book_practical_test')
            ->first();

        if (! $checklistItem) {
            return null;
        }

        $checklistItem->update([
            'date' => $date,
            'is_checked' => true,
        ]);

        return $checklistItem->refresh();
    }
}
