<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterStudentAction
{
    /**
     * Create a new user with the student role and an associated student record.
     *
     * @param  array{name: string, email: string, password: string, phone?: string}  $data
     * @return array{user: User, student: Student}
     */
    public function __invoke(array $data): array
    {
        return DB::transaction(function () use ($data): array {
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
    }
}
