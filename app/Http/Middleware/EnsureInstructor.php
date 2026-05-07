<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstructor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isInstructor()) {
            abort(403, 'Unauthorized. Instructor access required.');
        }

        if (! $user->instructor) {
            abort(403, 'Instructor profile not found.');
        }

        return $next($request);
    }
}
