<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\Student;
use App\Models\StudentChecklistItem;

class EnsureStudentChecklistAction
{
    /**
     * Lazy-seed the default checklist items for a student if they have none yet.
     *
     * Checklist items are created on demand the first time a student's checklist is
     * touched. Any flow that writes to a specific checklist item (e.g. carrying a
     * booked practical-test date over) must call this first, otherwise the item it
     * targets may not exist yet and the write silently no-ops.
     */
    public function __invoke(Student $student): void
    {
        if ($student->checklistItems()->exists()) {
            return;
        }

        $items = collect(StudentChecklistItem::defaultItems())->map(function (array $item) use ($student) {
            return [
                'student_id' => $student->id,
                'key' => $item['key'],
                'label' => $item['label'],
                'category' => $item['category'],
                'sort_order' => $item['sort_order'],
                'is_checked' => false,
                'date' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        StudentChecklistItem::insert($items);
    }
}
