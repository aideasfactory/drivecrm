<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Mail\InstructorWelcomeMail;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendInstructorWelcomeEmailAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Send the welcome / password-setup email to a newly added instructor.
     *
     * Flow:
     * 1. Mark the user's `welcome_email_pending` flag so admins can see if it never sends.
     * 2. Mint a password-broker reset token (Laravel's standard, time-limited token).
     * 3. Queue the welcome email with a link to the standard password.reset page.
     * 4. On success, clear the pending flag and log activity on the instructor.
     * 5. On failure, leave the pending flag set, log the error, and return false —
     *    callers (controllers / bulk-import) can surface the failure to the admin.
     *
     * Never throws — always returns success/failure so caller flows are tolerant.
     */
    public function __invoke(Instructor $instructor): bool
    {
        $user = $instructor->user;

        if (! $user || ! $user->email) {
            Log::warning('Cannot send instructor welcome email: missing user or email', [
                'instructor_id' => $instructor->id,
            ]);

            return false;
        }

        $user->forceFill(['welcome_email_pending' => true])->save();

        try {
            $token = Password::broker()->createToken($user);

            $setupUrl = route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]);

            $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);

            Mail::to($user->email)->queue(new InstructorWelcomeMail(
                user: $user,
                setupUrl: $setupUrl,
                expiresInMinutes: $expiresInMinutes,
            ));

            $user->forceFill(['welcome_email_pending' => false])->save();

            ($this->logActivity)(
                $instructor,
                "Welcome email sent to {$user->email}",
                'notification',
                [
                    'type' => 'instructor_welcome',
                    'recipient_email' => $user->email,
                ]
            );

            Log::info('Instructor welcome email queued', [
                'instructor_id' => $instructor->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send instructor welcome email', [
                'instructor_id' => $instructor->id,
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            ($this->logActivity)(
                $instructor,
                "Welcome email failed to send to {$user->email}",
                'notification',
                [
                    'type' => 'instructor_welcome_failed',
                    'recipient_email' => $user->email,
                    'error' => $e->getMessage(),
                ]
            );

            // Leave welcome_email_pending = true so admins can see the pending state.
            return false;
        }
    }
}
