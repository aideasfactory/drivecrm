<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictInstructor
{
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
        if (str_starts_with($path, "instructors/{$instructor->id}")) {
            return $next($request);
        }

        // Allow access to student detail routes (instructors view their pupils)
        if (str_starts_with($path, 'students/')) {
            return $next($request);
        }

        // Allow access to settings/profile routes
        if (str_starts_with($path, 'settings')) {
            return $next($request);
        }

        // Redirect everything else to the instructor's own page
        return redirect()->route('instructors.show', $instructor);
    }
}
