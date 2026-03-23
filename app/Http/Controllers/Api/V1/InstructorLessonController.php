<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InstructorDayLessonCollection;
use App\Services\InstructorService;
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
}
