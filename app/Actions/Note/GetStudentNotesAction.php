<?php

declare(strict_types=1);

namespace App\Actions\Note;

use App\Models\Note;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class GetStudentNotesAction
{
    /**
     * Get all notes for a student, ordered by most recent first.
     *
     * @return Collection<int, Note>
     */
    public function __invoke(Student $student): Collection
    {
        return $student->notes()
            ->latest()
            ->get();
    }
}
