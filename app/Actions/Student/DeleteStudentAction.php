<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Student;

class DeleteStudentAction
{
    /**
     * Delete a student record.
     */
    public function __invoke(Student $student): bool
    {
        return $student->delete();
    }
}
