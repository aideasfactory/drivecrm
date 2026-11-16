<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Instructor;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictInstructor
{
    /**
     * Routes a previous instructor (e.g. before a pupil transfer) may still use
     * for a pupil while a lesson they taught waits for sign-off. The sign-off
     * itself is further limited to the lessons they taught (LessonPolicy).
     *
     * @var list<string>
     */
    public const PREVIOUS_INSTRUCTOR_STUDENT_ROUTES = [
        'students.show',
        'students.lessons',
        'students.lessons.sign-off',
    ];

    /**
     * Redirect instructor-role users to their own instructor page
     * when they attempt to access non-allowed routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isInstructor()) {
            return $next($request);
        }

        $instructor = $user->instructor;

        if (! $instructor) {
            abort(403, 'Instructor profile not found.');
        }

        $path = $request->path();

        // Allow access to the instructor's own routes
        if ($path === "instructors/{$instructor->id}" || str_starts_with($path, "instructors/{$instructor->id}/")) {
            return $next($request);
        }

        // Allow access to student detail routes for the instructor's own pupils
        if (str_starts_with($path, 'students/')) {
            $this->ensureCanAccessStudent($request, $instructor);

            return $next($request);
        }

        // Allow access to settings/profile routes
        if (str_starts_with($path, 'settings')) {
            return $next($request);
        }

        // Redirect everything else to the instructor's own page
        return redirect()->route('instructors.show', $instructor);
    }

    /**
     * Instructors only reach their own pupils, plus the lessons and sign-off
     * of a pupil transferred away while a lesson they taught is unsigned.
     */
    private function ensureCanAccessStudent(Request $request, Instructor $instructor): void
    {
        $studentParameter = $request->route('student');

        if ($studentParameter === null) {
            return;
        }

        $student = $studentParameter instanceof Student
            ? $studentParameter
            : Student::query()->find($studentParameter);

        if (! $student || $student->instructor_id === $instructor->id) {
            return;
        }

        if (
            $request->routeIs(...self::PREVIOUS_INSTRUCTOR_STUDENT_ROUTES)
            && $student->hasSignOffDueForPreviousInstructor($instructor)
        ) {
            return;
        }

        abort(403, 'You can only access your own pupils.');
    }
}
