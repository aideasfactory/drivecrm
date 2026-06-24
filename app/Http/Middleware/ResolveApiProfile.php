<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiProfile
{
    /**
     * Eager-load the authenticated user's role-specific profile.
     *
     * After this middleware runs, controllers can access:
     *   $request->user()->profile     — Instructor|Student|null
     *   $request->user()->instructor  — Instructor|null (if instructor)
     *   $request->user()->student     — Student|null (if student)
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $relation = match ($user->role) {
                UserRole::INSTRUCTOR => 'instructor',
                UserRole::STUDENT => 'student',
                default => null,
            };

            if ($relation) {
                $user->load($relation);
            }

            if ($user->role === UserRole::STUDENT) {
                $this->touchStudentAppActivity($user->student);
            }
        }

        return $next($request);
    }

    /**
     * Record that the student is active in the mobile app.
     *
     * Any authenticated request reaching the V1 API can only originate from the
     * mobile app, so a successful request proves the app is installed and signed
     * in. The write is throttled to avoid a database update on every request.
     */
    private function touchStudentAppActivity(?Student $student): void
    {
        if (! $student) {
            return;
        }

        if ($student->app_last_active_at && $student->app_last_active_at->gt(now()->subMinutes(15))) {
            return;
        }

        $student->forceFill(['app_last_active_at' => now()])->saveQuietly();
    }
}
