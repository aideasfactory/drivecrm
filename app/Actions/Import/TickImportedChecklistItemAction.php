<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Student\Checklist\EnsureStudentChecklistAction;
use App\Models\Student;

class TickImportedChecklistItemAction
{
    public function __construct(
        protected EnsureStudentChecklistAction $ensureStudentChecklist,
    ) {}

    /**
     * Tick one of the pupil's checklist items (e.g. `sit_theory_test`) from
     * imported data. Only ever ticks: an item already ticked is left as it
     * is, its date and notes are never touched, and nothing is unticked.
     * Seeds the default checklist first if the pupil has none yet.
     *
     * @param  bool  $dryRun  Only report whether it would change
     * @return bool Whether the item was (or would be) ticked
     */
    public function __invoke(Student $student, string $key, bool $dryRun = false): bool
    {
        $item = $student->checklistItems()->where('key', $key)->first();

        if ($item?->is_checked) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        if ($item === null) {
            ($this->ensureStudentChecklist)($student);
            $item = $student->checklistItems()->where('key', $key)->first();
        }

        return $item !== null && $item->update(['is_checked' => true]);
    }
}
