<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreatePupilAction
{
    /**
     * Create a new pupil (user + student record) for an instructor.
     *
     * @param  Instructor  $instructor  The instructor to assign the pupil to
     * @param  array  $data  Pupil data (first_name, surname, email, phone, owns_account)
     * @return Student The created student record
     */
    public function __invoke(Instructor $instructor, array $data): Student
    {
        $user = User::create([
            'name' => $data['first_name'].' '.$data['surname'],
            'email' => $data['email'],
            'password' => Hash::make('password'),
            'role' => UserRole::STUDENT,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'instructor_id' => $instructor->id,
            'first_name' => $data['first_name'],
            'surname' => $data['surname'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'owns_account' => $data['owns_account'] ?? true,
        ]);
    }
}
