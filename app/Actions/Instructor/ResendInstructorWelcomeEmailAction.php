<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ResendInstructorWelcomeEmailAction
{
    /**
     * Minimum gap between two resends to the same instructor, guarding against
     * double clicks and two admins chasing the same person at once.
     */
    public const COOLDOWN_SECONDS = 120;

    public function __construct(
        protected SendInstructorWelcomeEmailAction $sendInstructorWelcomeEmail
    ) {}

    /**
     * Admin-triggered resend of the onboarding (welcome / password-setup) email.
     *
     * Allowed while the instructor hasn't signed up yet, or while the original
     * send is still flagged as failed. Each send mints a fresh password-broker
     * token, which replaces any earlier (possibly expired) setup link.
     *
     * @return bool True when the email was queued
     *
     * @throws ValidationException When the instructor has already signed up
     * @throws ThrottleRequestsException When resent within the cooldown window
     */
    public function __invoke(Instructor $instructor): bool
    {
        $user = $instructor->user;

        if ($user && $user->hasCompletedSignUp() && ! $user->welcome_email_pending) {
            throw ValidationException::withMessages([
                'instructor' => "{$user->name} has already signed up, so there's no onboarding email to resend.",
            ]);
        }

        $throttleKey = 'instructor-welcome-resend:'.$instructor->id;

        if (RateLimiter::hit($throttleKey, self::COOLDOWN_SECONDS) > 1) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw new ThrottleRequestsException(
                "The onboarding email was sent moments ago. You can resend it again in {$seconds} seconds."
            );
        }

        $sent = ($this->sendInstructorWelcomeEmail)($instructor);

        if (! $sent) {
            RateLimiter::clear($throttleKey);
        }

        return $sent;
    }
}
