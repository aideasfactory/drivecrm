<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Instructor;
use App\Models\Student;

class AttachStudentToInstructorAction
{
    /**
     * Attach a student to an instructor by setting instructor_id.
     *
     * Assumes the instructor has already been resolved from the PIN.
     */
    public function __invoke(Student $student, Instructor $instructor): Student
    {
        $student->update([
            'instructor_id' => $instructor->id,
        ]);

        $student->logActivity(
            "Student attached to instructor {$instructor->name} via PIN",
            'student',
            [
                'instructor_id' => $instructor->id,
                'instructor_name' => $instructor->name,
            ]
        );

        return $student->fresh();
    }
}
