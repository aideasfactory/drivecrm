<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InstructorDayLessonCollection;
use App\Services\InstructorService;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Notify the student that the instructor is on their way.
     */
    public function notifyOnWay(Request $request, Lesson $lesson): JsonResponse
    {
        $instructor = $request->user()->instructor;

        abort_unless($lesson->instructor_id === $instructor->id, 403, 'This lesson does not belong to you.');

        $this->instructorService->notifyStudentOnWay($instructor, $lesson);

        return response()->json([
            'message' => 'On-way notification logged successfully.',
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
            'message' => 'Arrived notification logged successfully.',
        ]);
    }
}
