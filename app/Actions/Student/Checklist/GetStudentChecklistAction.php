<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\Student;
use App\Models\StudentChecklistItem;
use Illuminate\Database\Eloquent\Collection;

class GetStudentChecklistAction
{
    /**
     * Get all checklist items for a student, lazy-seeding defaults if none exist.
     */
    public function __invoke(Student $student): Collection
    {
        if ($student->checklistItems()->count() === 0) {
            $this->seedDefaults($student);
        }

        return $student->checklistItems()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Seed the default checklist items for a student.
     */
    protected function seedDefaults(Student $student): void
    {
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
