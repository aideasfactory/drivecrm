<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Mail\InstructorWelcomeMail;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

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
     * 2. Sign a set-up URL valid for `auth.instructor_welcome_link_expire_days`.
     *    Clicking it mints a standard password-broker token and opens password.reset.
     * 3. Queue the welcome email with that link and its expiry date.
     * 4. On success, clear the pending flag, stamp `welcome_email_last_sent_at`
     *    and log activity on the instructor.
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
            $linkExpiresAt = now()->addDays((int) config('auth.instructor_welcome_link_expire_days', 28));

            $setupUrl = URL::temporarySignedRoute(
                'instructor.welcome.setup',
                $linkExpiresAt,
                ['user' => $user->id],
            );

            Mail::to($user->email)->queue(new InstructorWelcomeMail(
                user: $user,
                setupUrl: $setupUrl,
                linkExpiresAt: $linkExpiresAt,
            ));

            $user->forceFill([
                'welcome_email_pending' => false,
                'welcome_email_last_sent_at' => now(),
            ])->save();

            ($this->logActivity)(
                $instructor,
                "Welcome email sent to {$user->email}",
                'notification',
                [
                    'type' => 'instructor_welcome',
                    'recipient_email' => $user->email,
                ],
                'Welcome email sent'
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
