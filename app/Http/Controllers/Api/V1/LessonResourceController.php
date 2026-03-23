<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignLessonResourcesRequest;
use App\Http\Resources\V1\LessonResourceResource;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\ResourceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonResourceController extends Controller
{
    public function __construct(
        protected ResourceApiService $resourceService
    ) {}

    /**
     * Assign resources to a lesson and email the student.
     *
     * Only the student's assigned instructor can assign resources.
     */
    public function store(AssignLessonResourcesRequest $request, Student $student, int $lessonId): JsonResponse
    {
        Gate::authorize('assignResources', [Lesson::class, $student]);

        $lesson = Lesson::query()
            ->whereHas('order', fn ($q) => $q->where('student_id', $student->id))
            ->findOrFail($lessonId);

        $this->resourceService->assignResourcesToLesson(
            $lesson,
            $request->validated('resource_ids'),
            $student
        );

        $lesson->load('resources');

        return response()->json([
            'message' => 'Resources assigned successfully.',
            'data' => LessonResourceResource::collection($lesson->resources),
        ]);
    }
}
