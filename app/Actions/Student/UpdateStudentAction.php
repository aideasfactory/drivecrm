<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;

class UpdateStudentAction
{
    /**
     * Update an existing student record.
     *
     * @param  array{first_name?: string, surname?: string, email?: string|null, phone?: string|null, contact_first_name?: string|null, contact_surname?: string|null, contact_email?: string|null, contact_phone?: string|null, owns_account?: bool}  $data
     */
    public function __invoke(Student $student, array $data): Student
    {
        $student->update($data);

        $student->logActivity(
            'Student profile updated',
            'profile',
            ['updated_fields' => array_keys($data)]
        );

        return $student->fresh(['user', 'instructor.user']);
    }
}
