<?php

declare(strict_types=1);

namespace App\Actions\Student\Status;

use App\Models\Student;

class RemoveStudentFromInstructorAction
{
    /**
     * Remove a student from their assigned instructor by setting instructor_id to null.
     *
     * Does not delete the student record — only detaches from the instructor.
     */
    public function __invoke(Student $student): Student
    {
        if (! $student->instructor_id) {
            return $student;
        }

        $instructorName = $student->instructor?->user?->name ?? 'Unknown';

        $student->update([
            'instructor_id' => null,
        ]);

        $student->logActivity(
            "Student removed from instructor {$instructorName}",
            'student',
            [
                'instructor_name' => $instructorName,
            ]
        );

        return $student->fresh();
    }
}
