<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Shared\LogActivityAction;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentRegisteredNotification;
use Illuminate\Support\Facades\DB;

class RegisterStudentAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Create a new user with the student role and an associated student record.
     *
     * @param  array{name: string, email: string, password: string, phone?: string}  $data
     * @return array{user: User, student: Student}
     */
    public function __invoke(array $data): array
    {
        $result = DB::transaction(function () use ($data): array {
            $nameParts = explode(' ', $data['name'], 2);
            $firstName = $nameParts[0];
            $surname = $nameParts[1] ?? '';

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::STUDENT,
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'surname' => $surname,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'owns_account' => true,
                'status' => 'active',
            ]);

            return [
                'user' => $user,
                'student' => $student,
            ];
        });

        // Send welcome email
        $result['user']->notify(new StudentRegisteredNotification);

        // Log notification activity
        ($this->logActivity)(
            $result['student'],
            "Welcome email sent to {$result['user']->email}",
            'notification',
            [
                'type' => 'student_registered',
                'recipient_email' => $result['user']->email,
            ]
        );

        return $result;
    }
}
