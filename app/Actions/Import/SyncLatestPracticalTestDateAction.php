<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Student\Checklist\SyncPracticalTestDateToChecklistAction;
use App\Models\Student;

class SyncLatestPracticalTestDateAction
{
    public function __construct(
        protected SyncPracticalTestDateToChecklistAction $syncPracticalTestDateToChecklist,
    ) {}

    /**
     * Tick and date the pupil's "Book practical test" checklist item with an
     * imported test date, unless it already holds the same or a later date
     * (the latest test wins). "Sit practical test" is left alone.
     *
     * @param  string  $date  Y-m-d
     * @param  bool  $dryRun  Only report whether it would change
     * @return bool Whether the item was (or would be) updated
     */
    public function __invoke(Student $student, string $date, bool $dryRun = false): bool
    {
        $current = $student->checklistItems()->where('key', 'book_practical_test')->first();

        if ($current?->is_checked && $current->date !== null && $current->date->toDateString() >= $date) {
            return false;
        }

        if (! $dryRun) {
            ($this->syncPracticalTestDateToChecklist)($student, $date);
        }

        return true;
    }
}
