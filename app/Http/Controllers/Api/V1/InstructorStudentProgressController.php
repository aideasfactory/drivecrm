<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveStudentProgressRequest;
use App\Http\Resources\V1\StudentProgressResource;
use App\Models\Student;
use App\Services\ProgressTrackerService;
use Illuminate\Http\JsonResponse;

class InstructorStudentProgressController extends Controller
{
    public function __construct(
        protected ProgressTrackerService $progressTrackerService,
    ) {}

    /**
     * Return a specific student's progress for the authenticated instructor
     * (GET /api/v1/instructor/students/{student}/progress).
     */
    public function show(Student $student): JsonResponse
    {
        $this->ensureOwnsStudent($student);

        $categories = $this->progressTrackerService->getStudentProgress($student);

        return StudentProgressResource::collection($categories)
            ->additional(['score_labels' => config('progress_tracker.score_labels', [])])
            ->response();
    }

    /**
     * Bulk-save scores for a student
     * (POST /api/v1/instructor/students/{student}/progress).
     */
    public function update(SaveStudentProgressRequest $request, Student $student): JsonResponse
    {
        $this->ensureOwnsStudent($student);

        $this->progressTrackerService->saveStudentProgress(
            $student,
            $request->validated('scores'),
        );

        $categories = $this->progressTrackerService->getStudentProgress($student);

        return StudentProgressResource::collection($categories)
            ->additional(['score_labels' => config('progress_tracker.score_labels', [])])
            ->response();
    }

    private function ensureOwnsStudent(Student $student): void
    {
        $instructor = request()->user()->instructor;

        if ($instructor === null || $student->instructor_id !== $instructor->id) {
            abort(403, 'You do not teach this student.');
        }
    }
}
