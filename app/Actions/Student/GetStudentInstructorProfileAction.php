<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Instructor;
use App\Models\Student;

class GetStudentInstructorProfileAction
{
    /**
     * Fetch the public profile of the student's attached instructor.
     *
     * Returns the Instructor model with the user relationship loaded,
     * or null if the student has no attached instructor.
     */
    public function __invoke(Student $student): ?Instructor
    {
        if (! $student->instructor_id) {
            return null;
        }

        return Instructor::with('user:id,name')
            ->find($student->instructor_id);
    }
}
