<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentNoteRequest;
use App\Http\Requests\Api\V1\UpdateStudentNoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Note;
use App\Models\Student;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentNoteController extends Controller
{
    public function __construct(
        protected NoteService $noteService
    ) {}

    /**
     * Return all notes for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function index(Student $student): AnonymousResourceCollection
    {
        Gate::authorize('view', $student);

        $notes = $this->noteService->getStudentNotes($student);

        return NoteResource::collection($notes->getCollection());
    }

    /**
     * Store a new note for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function store(StoreStudentNoteRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $note = $this->noteService->createStudentNote(
            $student,
            $request->validated('note')
        );

        return (new NoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing note for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     * The note must belong to the given student.
     */
    public function update(UpdateStudentNoteRequest $request, Student $student, Note $note): NoteResource
    {
        Gate::authorize('view', $student);

        $this->ensureNoteBelongsToStudent($note, $student);

        $updated = $this->noteService->updateStudentNote(
            $note,
            $request->validated('note')
        );

        return new NoteResource($updated);
    }

    /**
     * Delete a note for a student (soft delete).
     *
     * Authorised for the student themselves or their assigned instructor.
     * The note must belong to the given student.
     */
    public function destroy(Student $student, Note $note): JsonResponse
    {
        Gate::authorize('view', $student);

        $this->ensureNoteBelongsToStudent($note, $student);

        $this->noteService->deleteStudentNote($note);

        return response()->json(null, 204);
    }

    /**
     * Verify the note belongs to the given student.
     *
     * @throws NotFoundHttpException
     */
    private function ensureNoteBelongsToStudent(Note $note, Student $student): void
    {
        if ($note->noteable_type !== Student::class || $note->noteable_id !== $student->id) {
            throw new NotFoundHttpException('Note not found for this student.');
        }
    }
}
