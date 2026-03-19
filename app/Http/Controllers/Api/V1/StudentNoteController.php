<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStudentNoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Student;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

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

        return NoteResource::collection($notes);
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
}
