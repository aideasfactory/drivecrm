<?php

declare(strict_types=1);

namespace App\Actions\Note;

use App\Models\Note;
use App\Models\Student;

class CreateStudentNoteAction
{
    /**
     * Create a new note attached to the given student.
     */
    public function __invoke(Student $student, string $note): Note
    {
        return $student->notes()->create([
            'note' => $note,
        ]);
    }
}
