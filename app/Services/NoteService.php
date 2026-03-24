<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Shared\Note\CreateNoteAction;
use App\Actions\Shared\Note\DeleteNoteAction;
use App\Actions\Shared\Note\GetNotesAction;
use App\Actions\Shared\Note\UpdateNoteAction;
use App\Models\Note;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NoteService extends BaseService
{
    public function __construct(
        protected GetNotesAction $getNotes,
        protected CreateNoteAction $createNote,
        protected UpdateNoteAction $updateNote,
        protected DeleteNoteAction $deleteNote
    ) {}

    /**
     * Get paginated notes for a student.
     *
     * @return LengthAwarePaginator<int, Note>
     */
    public function getStudentNotes(Student $student, int $perPage = 50): LengthAwarePaginator
    {
        return ($this->getNotes)($student, $perPage);
    }

    /**
     * Create a new note on a student (with activity logging).
     */
    public function createStudentNote(Student $student, string $note): Note
    {
        return ($this->createNote)($student, $note);
    }

    /**
     * Update an existing note (with activity logging).
     */
    public function updateStudentNote(Note $note, string $content): Note
    {
        return ($this->updateNote)($note, $content);
    }

    /**
     * Soft delete a note.
     */
    public function deleteStudentNote(Note $note): void
    {
        ($this->deleteNote)($note);
    }
}
