<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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
        }

        return $next($request);
    }
}
