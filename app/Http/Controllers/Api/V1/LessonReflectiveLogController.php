<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveReflectiveLogRequest;
use App\Http\Resources\V1\LessonDetailResource;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\LessonSignOffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonReflectiveLogController extends Controller
{
    public function __construct(
        protected LessonSignOffService $lessonSignOffService
    ) {}

    /**
     * Upsert the reflective log for a lesson and return the refreshed lesson.
     *
     * Only the student's assigned instructor can write the log. Completing
     * the three required prompts persists immediately so the client can
     * clear "Reflective log not completed" before sign-off.
     */
    public function upsert(SaveReflectiveLogRequest $request, Student $student, int $lessonId): JsonResponse
    {
        Gate::authorize('saveReflectiveLog', [Lesson::class, $student]);

        $lesson = Lesson::query()
            ->whereHas('order', fn ($q) => $q->where('student_id', $student->id))
            ->whereNotIn('status', [LessonStatus::CANCELLED, LessonStatus::DRAFT])
            ->findOrFail($lessonId);

        $this->lessonSignOffService->saveReflectiveLog($lesson, $request->safe()->only([
            'what_i_learned',
            'what_went_well',
            'what_to_improve',
            'additional_notes',
        ]));

        $lesson = $this->lessonSignOffService->getLessonDetail($student, $lesson->id);

        return (new LessonDetailResource($lesson))
            ->additional(['message' => 'Reflective log saved.'])
            ->response();
    }
}
