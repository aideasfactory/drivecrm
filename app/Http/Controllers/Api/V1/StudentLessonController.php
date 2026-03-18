<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignOffLessonRequest;
use App\Http\Resources\V1\LessonCollection;
use App\Http\Resources\V1\LessonDetailResource;
use App\Jobs\ProcessLessonSignOffJob;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\LessonSignOffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentLessonController extends Controller
{
    public function __construct(
        protected LessonSignOffService $lessonSignOffService
    ) {}

    /**
     * Return all lessons for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function index(Request $request, Student $student): LessonCollection
    {
        Gate::authorize('viewAny', [Lesson::class, $student]);

        $lessons = $this->lessonSignOffService->getStudentLessons($student);

        return new LessonCollection($lessons);
    }

    /**
     * Return a single lesson detail for a student.
     *
     * Authorised for the student themselves or their assigned instructor.
     */
    public function show(Request $request, Student $student, int $lesson): LessonDetailResource
    {
        Gate::authorize('view', [Lesson::class, $student]);

        $lesson = $this->lessonSignOffService->getLessonDetail($student, $lesson);

        return new LessonDetailResource($lesson);
    }

    /**
     * Sign off a lesson as completed, triggering payout, notifications, and AI recommendations.
     *
     * Authorised for the assigned instructor only.
     */
    public function signOff(SignOffLessonRequest $request, Student $student, Lesson $lesson): JsonResponse
    {
        Gate::authorize('signOff', [Lesson::class, $student]);

        // Verify lesson belongs to this student (via order)
        $lessonBelongsToStudent = $student->orders()
            ->whereHas('lessons', fn ($q) => $q->where('id', $lesson->id))
            ->exists();

        if (! $lessonBelongsToStudent) {
            return response()->json(['message' => 'Lesson not found for this student.'], 404);
        }

        if ($lesson->isCompleted()) {
            return response()->json(['message' => 'This lesson has already been completed.'], 422);
        }

        $instructor = $request->user()->instructor;

        if (! $instructor) {
            return response()->json(['message' => 'No instructor profile found.'], 422);
        }

        ProcessLessonSignOffJob::dispatch($lesson, $instructor, $request->validated('summary'));

        return response()->json([
            'message' => 'Lesson sign-off is being processed.',
        ]);
    }
}
