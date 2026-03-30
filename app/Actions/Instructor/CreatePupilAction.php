<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use App\Notifications\WelcomeStudentNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePupilAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Create a new pupil (user + student record) for an instructor.
     *
     * @param  Instructor  $instructor  The instructor to assign the pupil to
     * @param  array  $data  Pupil data (first_name, surname, email, phone, owns_account)
     * @return Student The created student record
     */
    public function __invoke(Instructor $instructor, array $data): Student
    {
        $temporaryPassword = Str::random(12);

        $user = User::create([
            'name' => $data['first_name'].' '.$data['surname'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPassword),
            'role' => UserRole::STUDENT,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'instructor_id' => $instructor->id,
            'first_name' => $data['first_name'],
            'surname' => $data['surname'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'owns_account' => $data['owns_account'] ?? true,
        ]);

        $user->notify(new WelcomeStudentNotification($temporaryPassword, $instructor));

        // Log notification activity for student
        ($this->logActivity)(
            $student,
            "Welcome email sent to {$user->email}",
            'notification',
            [
                'type' => 'welcome_student',
                'recipient_email' => $user->email,
                'instructor_id' => $instructor->id,
            ]
        );

        // Log notification activity for instructor
        ($this->logActivity)(
            $instructor,
            "Welcome email sent to new student {$data['first_name']} {$data['surname']} ({$user->email})",
            'notification',
            [
                'type' => 'welcome_student',
                'recipient_email' => $user->email,
                'student_id' => $student->id,
            ]
        );

        return $student;
    }
}
