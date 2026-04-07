<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachToInstructorRequest;
use App\Http\Requests\Api\V1\StoreStudentRequest;
use App\Http\Requests\Api\V1\UpdateStudentRequest;
use App\Http\Resources\V1\StudentResource;
use App\Models\Instructor;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function __construct(
        protected StudentService $studentService
    ) {}

    /**
     * Create a new student record.
     *
     * Only authenticated instructors can create students.
     * The student is automatically assigned to the authenticated instructor.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        Gate::authorize('create', \App\Models\Student::class);

        $instructor = $request->user()->instructor;

        $student = $this->studentService->create($instructor, $request->validated());

        return (new StudentResource($student))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Return a single student record.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function show(Request $request, int $id): StudentResource
    {
        $student = $this->studentService->getById($id);

        Gate::authorize('view', $student);

        return new StudentResource($student);
    }

    /**
     * Update an existing student record.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function update(UpdateStudentRequest $request, int $id): StudentResource
    {
        $student = $this->studentService->getById($id);

        Gate::authorize('update', $student);

        $student = $this->studentService->update($student, $request->validated());

        return new StudentResource($student);
    }

    /**
     * Attach the authenticated student to an instructor via PIN.
     *
     * Only students without an assigned instructor can use this endpoint.
     */
    public function attachToInstructor(AttachToInstructorRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        if (! $student) {
            return response()->json([
                'message' => 'No student profile is associated with your account.',
            ], 422);
        }

        if ($student->hasInstructor()) {
            return response()->json([
                'message' => 'You are already attached to an instructor.',
            ], 422);
        }

        $instructor = Instructor::where('pin', $request->validated('pin'))->first();

        if (! $instructor) {
            return response()->json([
                'message' => 'The PIN you entered does not match any instructor.',
            ], 422);
        }

        $this->studentService->attachToInstructor($student, $instructor);

        return response()->json(true);
    }

    /**
     * Remove a student from the instructor (soft remove, not hard delete).
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $student = $this->studentService->getById($id);

        Gate::authorize('delete', $student);

        $student = $this->studentService->remove($student);

        return (new StudentResource($student))
            ->response()
            ->setStatusCode(200);
    }
}
