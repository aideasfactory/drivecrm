<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Note\CreateStudentNoteAction;
use App\Actions\Note\GetStudentNotesAction;
use App\Models\Note;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class NoteService extends BaseService
{
    public function __construct(
        protected GetStudentNotesAction $getStudentNotes,
        protected CreateStudentNoteAction $createStudentNote
    ) {}

    /**
     * Get all notes for a student.
     *
     * @return Collection<int, Note>
     */
    public function getStudentNotes(Student $student): Collection
    {
        return ($this->getStudentNotes)($student);
    }

    /**
     * Create a new note on a student.
     */
    public function createStudentNote(Student $student, string $note): Note
    {
        return ($this->createStudentNote)($student, $note);
    }
}
