<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignOffLessonRequest;
use App\Jobs\ProcessLessonSignOffJob;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonSignOffController extends Controller
{
    /**
     * Sign off a lesson as completed.
     *
     * Dispatches the same async job as the admin area, triggering the full
     * chain: mark completed, calendar update, Stripe payout, activity logs,
     * feedback email, and AI resource recommendations.
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

        ProcessLessonSignOffJob::dispatch($lesson, $instructor, $request->validated('summary'));

        return response()->json([
            'message' => 'Lesson sign-off is being processed.',
        ]);
    }
}
