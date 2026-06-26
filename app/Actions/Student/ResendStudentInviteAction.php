<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Actions\Shared\LogActivityAction;
use App\Models\Student;
use App\Notifications\WelcomeStudentNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResendStudentInviteAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Resend the app login invite to an existing student.
     *
     * Mirrors the welcome email sent on initial creation. Because the original
     * temporary password is never stored, a fresh temporary password is issued,
     * the user is flagged to change it on next sign-in, and the welcome
     * notification is re-sent.
     *
     * @param  Student  $student  The student to re-invite
     * @return bool True when the invite was re-sent; false when the student has
     *              no linked user account or no assigned instructor.
     */
    public function __invoke(Student $student): bool
    {
        $user = $student->user;
        $instructor = $student->instructor;

        if (! $user || ! $instructor) {
            return false;
        }

        $temporaryPassword = Str::random(12);

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'password_change_required' => true,
        ]);

        $user->notify(new WelcomeStudentNotification($temporaryPassword, $instructor));

        ($this->logActivity)(
            $student,
            "Invite re-sent to {$user->email}",
            'notification',
            [
                'type' => 'resend_student_invite',
                'recipient_email' => $user->email,
                'instructor_id' => $instructor->id,
            ]
        );

        ($this->logActivity)(
            $instructor,
            "Invite re-sent to student {$student->first_name} {$student->surname} ({$user->email})",
            'notification',
            [
                'type' => 'resend_student_invite',
                'recipient_email' => $user->email,
                'student_id' => $student->id,
            ]
        );

        return true;
    }
}
