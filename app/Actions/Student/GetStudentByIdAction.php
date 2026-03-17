<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;

class GetStudentByIdAction
{
    /**
     * Find a student by ID with key relationships eager-loaded.
     */
    public function __invoke(int $id): Student
    {
        return Student::query()
            ->with(['user', 'instructor.user'])
            ->findOrFail($id);
    }
}
