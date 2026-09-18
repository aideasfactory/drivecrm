<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InstructorNotOnboardedException;
use App\Exceptions\LessonAlreadyCompletedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignOffLessonRequest;
use App\Http\Resources\V1\LessonDetailResource;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\LessonSignOffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonSignOffController extends Controller
{
    public function __construct(
        protected LessonSignOffService $lessonSignOffService
    ) {}

    /**
     * Sign off a lesson as completed.
     *
     * Same body and pipeline as admin CRM: { "summary": "..." } only.
     * Runs the existing LessonSignOffService in this request so the app
     * receives the completed lesson instead of "being processed".
     * Admin still queues ProcessLessonSignOffJob — that job is unchanged.
     */
    public function store(SignOffLessonRequest $request, Student $student, int $lessonId): JsonResponse
    {
        Gate::authorize('signOff', [Lesson::class, $student]);

        $lesson = Lesson::query()
            ->whereHas('order', fn ($q) => $q->where('student_id', $student->id))
            ->where('status', 'pending')
            ->findOrFail($lessonId);

        $instructor = $lesson->instructor;

        if (! $instructor) {
            return response()->json(['message' => 'No instructor assigned to this lesson.'], 422);
        }

        $summary = $request->validated('summary');

        try {
            $this->lessonSignOffService->signOffLesson($lesson, $instructor, $summary);
        } catch (LessonAlreadyCompletedException|InstructorNotOnboardedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $lesson = $this->lessonSignOffService->getLessonDetail($student, $lesson->id);

        return (new LessonDetailResource($lesson))
            ->additional(['message' => 'Lesson signed off.'])
            ->response();
    }
}
