<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LessonCollection;
use App\Http\Resources\V1\LessonDetailResource;
use App\Models\Lesson;
use App\Models\Student;
use App\Services\LessonSignOffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentLessonController extends Controller
{
    public function __construct(
        protected LessonSignOffService $lessonSignOffService
    ) {}

    /**
     * Return lessons for a student, with optional filtering.
     *
     * Query params: status, from_date, sort (asc|desc), limit.
     * Authorised for the student themselves or their assigned instructor.
     */
    public function index(Request $request, Student $student): LessonCollection
    {
        Gate::authorize('viewAny', [Lesson::class, $student]);

        $filters = array_filter([
            'status' => $request->query('status'),
            'from_date' => $request->query('from_date'),
            'sort' => $request->query('sort'),
            'limit' => $request->query('limit') ? (int) $request->query('limit') : null,
        ], fn ($value) => $value !== null);

        $lessons = $this->lessonSignOffService->getStudentLessons($student, $filters);

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
}
