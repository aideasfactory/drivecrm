<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporarily block student users from accessing the CRM.
 *
 * This middleware should be removed once the student CRM experience is built out.
 */
class RestrictStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isStudent()) {
            // API requests get a JSON 403
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Student access is temporarily unavailable.',
                ], 403);
            }

            // Web requests: log out and redirect to login with error
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Student access to the CRM is temporarily unavailable. Please try again later.',
            ]);
        }

        return $next($request);
    }
}
