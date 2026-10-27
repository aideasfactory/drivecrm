<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelBankedLessonRequest;
use App\Http\Requests\Api\V1\GetInstructorLessonsRangeRequest;
use App\Http\Requests\Api\V1\RebookBankedLessonRequest;
use App\Http\Resources\V1\BankedLessonResource;
use App\Http\Resources\V1\InstructorDayLessonCollection;
use App\Models\Lesson;
use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class InstructorLessonController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Return the authenticated instructor's lessons for a given date.
     */
    public function index(Request $request, string $date): InstructorDayLessonCollection
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            throw ValidationException::withMessages([
                'date' => ['The date must be a valid date in Y-m-d format.'],
            ]);
        }

        $instructor = $request->user()->instructor;

        $lessons = $this->instructorService->getDayLessons($instructor, $date);

        return new InstructorDayLessonCollection($lessons);
    }

    /**
     * Return the authenticated instructor's lessons across an inclusive
     * ?from=&to= range (week view, max 31 days).
     */
    public function range(GetInstructorLessonsRangeRequest $request): InstructorDayLessonCollection
    {
        $instructor = $request->user()->instructor;

        $lessons = $this->instructorService->getLessonsInRange(
            $instructor,
            $request->validated('from'),
            $request->validated('to')
        );

        return new InstructorDayLessonCollection($lessons);
    }

    /**
     * Notify the student that the instructor is on their way.
     */
    public function notifyOnWay(Request $request, Lesson $lesson): JsonResponse
    {
        $instructor = $request->user()->instructor;

        abort_unless($lesson->instructor_id === $instructor->id, 403, 'This lesson does not belong to you.');

        $this->instructorService->notifyStudentOnWay($instructor, $lesson);

        return response()->json([
            'message' => 'On-way notification sent successfully.',
        ]);
    }

    /**
     * Notify the student that the instructor has arrived.
     */
    public function notifyArrived(Request $request, Lesson $lesson): JsonResponse
    {
        $instructor = $request->user()->instructor;

        abort_unless($lesson->instructor_id === $instructor->id, 403, 'This lesson does not belong to you.');

        $this->instructorService->notifyStudentArrived($instructor, $lesson);

        return response()->json([
            'message' => 'Arrived notification sent successfully.',
        ]);
    }

    /**
     * Update mileage for a completed lesson.
     */
    public function updateMileage(Request $request, Lesson $lesson): JsonResponse
    {
        $instructor = $request->user()->instructor;

        abort_unless($lesson->instructor_id === $instructor->id, 403, 'This lesson does not belong to you.');

        $validated = $request->validate([
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $lesson = $this->instructorService->updateLessonMileage($lesson, $validated['mileage'] ?? null);

        return response()->json([
            'message' => 'Mileage updated successfully.',
            'mileage' => $lesson->mileage,
        ]);
    }

    /**
     * The authenticated instructor's lessons kept to rebook later, longest
     * waiting first. Each needs rebooking with POST lessons/{lesson}/rebook.
     */
    public function banked(Request $request): AnonymousResourceCollection
    {
        $lessons = $this->instructorService->getBankedLessons($request->user()->instructor);

        return BankedLessonResource::collection($lessons);
    }

    /**
     * Put one of the instructor's banked lessons back in the diary.
     */
    public function rebook(RebookBankedLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $instructor = $request->user()->instructor;

        abort_unless($lesson->instructor_id === $instructor->id, 403, 'This lesson does not belong to you.');

        $lesson = $this->instructorService->rebookBankedLesson(
            $lesson,
            $request->validated('date'),
            $request->validated('start_time'),
            $request->validated('end_time'),
            $request->user(),
        );

        return response()->json([
            'message' => 'Lesson rebooked. The student has been emailed.',
            'lesson_id' => $lesson->id,
            'calendar_item_id' => $lesson->calendar_item_id,
            'date' => $lesson->date?->format('Y-m-d'),
            'start_time' => $lesson->start_time?->format('H:i'),
            'end_time' => $lesson->end_time?->format('H:i'),
        ]);
    }

    /**
     * Cancel one of the instructor's banked lessons. A paid one goes to Head
     * Office to refund the pupil.
     */
    public function cancelBanked(CancelBankedLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $instructor = $request->user()->instructor;

        abort_unless($lesson->instructor_id === $instructor->id, 403, 'This lesson does not belong to you.');

        try {
            $result = $this->instructorService->cancelBankedLesson(
                $lesson,
                $request->reasonCode(),
                $request->validated('reason'),
                $request->user(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => '1 lesson cancelled. The student has been notified.',
            'cancelled_count' => $result['cancelled_count'],
            'refund_required_count' => $result['refund_required_count'],
        ]);
    }
}
