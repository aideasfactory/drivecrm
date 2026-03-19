<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Instructor;
use App\Models\Student;

class CreateStudentAction
{
    /**
     * Create a new student record assigned to an instructor.
     *
     * @param  array{first_name: string, surname: string, email?: string|null, phone?: string|null, contact_first_name?: string|null, contact_surname?: string|null, contact_email?: string|null, contact_phone?: string|null, owns_account?: bool}  $data
     */
    public function __invoke(Instructor $instructor, array $data): Student
    {
        $student = Student::create([
            'instructor_id' => $instructor->id,
            'first_name' => $data['first_name'],
            'surname' => $data['surname'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'contact_first_name' => $data['contact_first_name'] ?? null,
            'contact_surname' => $data['contact_surname'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'owns_account' => $data['owns_account'] ?? true,
            'status' => 'active',
        ]);

        $student->logActivity(
            'Student record created by instructor',
            'profile',
            ['instructor_id' => $instructor->id]
        );

        return $student->load(['user', 'instructor.user']);
    }
}
