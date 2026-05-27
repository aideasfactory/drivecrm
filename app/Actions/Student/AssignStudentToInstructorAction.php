<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAssignedByAdminNotification;
use RuntimeException;

class AssignStudentToInstructorAction
{
    /**
     * Assign an unassigned student to an instructor. Used by admins from the
     * Students index when picking up an orphaned student. Re-assignment of a
     * student who already has an instructor should go through the Transfer
     * flow, which also handles future-lesson migration — this action will
     * refuse that case.
     */
    public function __invoke(Student $student, Instructor $instructor, User $admin): Student
    {
        if ($student->instructor_id !== null) {
            throw new RuntimeException('Student already has an instructor — use the transfer flow to re-assign.');
        }

        $instructor->loadMissing('user');

        $student->update(['instructor_id' => $instructor->id]);

        $studentDisplayName = trim("{$student->first_name} {$student->surname}")
            ?: ($student->email ?? "Student #{$student->id}");
        $instructorName = $instructor->name ?? "Instructor #{$instructor->id}";

        $metadata = [
            'instructor_id' => $instructor->id,
            'assigned_by_user_id' => $admin->id,
        ];

        $student->logActivity(
            "Assigned to {$instructorName} by admin",
            'instructor_assigned',
            $metadata,
        );

        $instructor->logActivity(
            "Student {$studentDisplayName} assigned by admin",
            'student_gained',
            $metadata,
        );

        if ($instructor->user) {
            $instructor->user->notify(new StudentAssignedByAdminNotification($student, $instructor));
        }

        return $student->fresh(['instructor.user']);
    }
}
