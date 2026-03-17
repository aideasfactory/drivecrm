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
        $this->authorize('viewAny', [Lesson::class, $student]);

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
        $this->authorize('view', [Lesson::class, $student]);

        $lesson = $this->lessonSignOffService->getLessonDetail($student, $lesson);

        return new LessonDetailResource($lesson);
    }
}
