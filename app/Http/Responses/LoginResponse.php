<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($user->isStudent()) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->wantsJson()
                ? response()->json(['message' => 'Students cannot access the web app.'], 403)
                : redirect()->route('no-access');
        }

        $redirectUrl = $this->resolveRedirectUrl($user);

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($redirectUrl);
    }

    /**
     * Resolve the post-login redirect URL based on the user's role.
     */
    private function resolveRedirectUrl(\App\Models\User $user): string
    {
        if ($user->isInstructor()) {
            $instructor = $user->instructor;

            if ($instructor) {
                return route('instructors.show', $instructor);
            }
        }

        return config('fortify.home', '/dashboard');
    }
}
