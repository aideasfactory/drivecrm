<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LessonStatus;
use App\Exceptions\InstructorNotOnboardedException;
use App\Exceptions\LessonAlreadyCompletedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignOffLessonRequest;
use App\Http\Resources\V1\LessonDetailResource;
use App\Jobs\ProcessLessonSignOffJob;
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
     * Persists the summary and reflective log immediately, marks the lesson
     * completed in the same request, and returns the refreshed lesson so the
     * client can clear Needs Sign Off. Stripe payout, emails, and AI
     * recommendations stay on the background job.
     */
    public function store(SignOffLessonRequest $request, Student $student, int $lessonId): JsonResponse
    {
        Gate::authorize('signOff', [Lesson::class, $student]);

        $lesson = Lesson::query()
            ->whereHas('order', fn ($q) => $q->where('student_id', $student->id))
            ->whereNotIn('status', [LessonStatus::CANCELLED])
            ->with(['order', 'lessonPayment', 'reflectiveLog', 'instructor'])
            ->findOrFail($lessonId);

        if ($lesson->isDraft()) {
            return response()->json(['message' => 'Draft lessons cannot be signed off until they are paid for.'], 422);
        }

        if ($lesson->isCompleted()) {
            return response()->json(['message' => 'This lesson has already been completed.'], 422);
        }

        if (! $lesson->isPending()) {
            return response()->json(['message' => 'This lesson cannot be signed off.'], 422);
        }

        $instructor = $lesson->instructor;

        if (! $instructor) {
            return response()->json(['message' => 'No instructor assigned to this lesson.'], 422);
        }

        $validated = $request->validated();

        $this->lessonSignOffService->saveLessonSummary($lesson, $validated['summary']);

        if (isset($validated['reflective_log'])) {
            $this->lessonSignOffService->saveReflectiveLog($lesson, $validated['reflective_log']);
            $lesson->unsetRelation('reflectiveLog');
        }

        $lesson->load('reflectiveLog');

        if (! $lesson->hasCompleteReflectiveLog()) {
            return response()->json([
                'message' => 'Reflective log must be completed before sign-off.',
                'errors' => [
                    'reflective_log' => ['Reflective log must be completed before sign-off.'],
                ],
            ], 422);
        }

        try {
            $this->lessonSignOffService->completeLesson($lesson);
        } catch (LessonAlreadyCompletedException|InstructorNotOnboardedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ProcessLessonSignOffJob::dispatch($lesson->fresh(), $instructor, $validated['summary']);

        $lesson = $this->lessonSignOffService->getLessonDetail($student, $lesson->id);

        return (new LessonDetailResource($lesson))
            ->additional(['message' => 'Lesson signed off.'])
            ->response();
    }
}
