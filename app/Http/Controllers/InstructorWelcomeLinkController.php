<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Public (signed-URL) landing route for the set-up link in the instructor
 * welcome email. The signature carries the long welcome expiry; a standard
 * password-broker token is only minted on click, so normal password reset
 * links keep their own shorter expiry. The signature is checked by hand so an
 * expired link lands on the forgot-password page instead of a 403.
 */
class InstructorWelcomeLinkController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('password.request')->with(
                'status',
                'Your account set-up link has expired. Enter your email below and we\'ll send you a new link to set your password.'
            );
        }

        if ($user->hasCompletedSignUp()) {
            return redirect()->route('login')->with(
                'status',
                'Your account is already set up. Sign in with your email and password, or use Forgot password if you need a new one.'
            );
        }

        return redirect()->route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);
    }
}
