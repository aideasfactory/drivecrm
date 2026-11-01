<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentResource;
use App\Models\Student;
use App\Services\InstructorService;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InstructorStudentController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService,
        protected StudentService $studentService
    ) {}

    /**
     * Get the authenticated instructor's students grouped by status.
     *
     * Pass `?active_only=1` for pickers that start a booking: every group then
     * holds only `status = active` students, so `passed` and `inactive` are empty.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active_only' => ['sometimes', 'boolean'],
        ]);

        $instructor = $request->user()->instructor;

        $grouped = $this->instructorService->getGroupedStudents($instructor);

        if ((bool) ($validated['active_only'] ?? false)) {
            $grouped = [
                'active' => $grouped['active'],
                'passed' => collect(),
                'inactive' => collect(),
                'recent_activity' => $grouped['recent_activity']
                    ->filter(fn (Student $student): bool => $student->isActive())
                    ->values(),
            ];
        }

        return response()->json([
            'data' => [
                'active' => StudentResource::collection($grouped['active']),
                'passed' => StudentResource::collection($grouped['passed']),
                'inactive' => StudentResource::collection($grouped['inactive']),
                'recent_activity' => StudentResource::collection($grouped['recent_activity']),
            ],
        ]);
    }

    /**
     * Remove one of the authenticated instructor's pupils from their list
     * (DELETE /api/v1/instructor/pupils/{student}). Same lookup, policy and
     * soft remove as DELETE /api/v1/students/{student}, restricted to
     * instructors and blocked while upcoming lessons remain.
     */
    public function destroy(Request $request, int $student): JsonResponse
    {
        $instructor = $request->user()->instructor;

        if ($instructor === null) {
            abort(403, 'You do not teach this pupil.');
        }

        $student = $this->studentService->getById($student);

        Gate::authorize('delete', $student);

        if ($this->studentService->hasUpcomingLessonsWith($student, $instructor)) {
            return response()->json([
                'message' => 'This pupil still has lessons with you that are not signed off or cancelled. Sign off or cancel them before removing the pupil.',
            ], 422);
        }

        $this->studentService->remove($student);

        return response()->json(['message' => 'Pupil removed']);
    }
}
