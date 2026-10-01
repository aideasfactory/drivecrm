<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictOwnerAccess
{
    /**
     * Admin sections a restricted owner may access.
     *
     * @var list<string>
     */
    public const ALLOWED_PATHS = [
        'instructors',
        'instructors/*',
        'pupils',
        'pupils/*',
        'students/*',
        'student-transfers',
        'student-transfers/*',
        'support-messages',
        'support-messages/*',
        'enquiries',
        'enquiries/*',
        'settings',
        'settings/*',
    ];

    /**
     * Keep restricted owners inside their permitted admin sections,
     * redirecting page visits to the instructors list.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isRestrictedOwner()) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED_PATHS)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your owner access does not include this area.');
        }

        return redirect()->route('instructors.index');
    }
}
