<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Instructor\SendInstructorWelcomeEmailAction;
use App\Actions\Student\ResendStudentInviteAction;
use App\Enums\UserRole;
use App\Models\User;

class SendImportedWelcomeEmailAction
{
    public function __construct(
        protected SendInstructorWelcomeEmailAction $sendInstructorWelcomeEmail,
        protected ResendStudentInviteAction $resendStudentInvite,
    ) {}

    /**
     * Send the held welcome email to one imported user, reusing the normal
     * senders:
     * - Instructor → password-setup link (clears `welcome_email_pending` itself).
     * - Student → username + fresh temporary password; the flag is cleared here
     *   because the student invite action does not track it.
     *
     * @return bool True when the email was queued
     */
    public function __invoke(User $user): bool
    {
        if ($user->role === UserRole::INSTRUCTOR && $user->instructor) {
            return ($this->sendInstructorWelcomeEmail)($user->instructor);
        }

        if ($user->role === UserRole::STUDENT && $user->student) {
            if (! ($this->resendStudentInvite)($user->student)) {
                return false;
            }

            $user->forceFill(['welcome_email_pending' => false])->save();

            return true;
        }

        return false;
    }
}
