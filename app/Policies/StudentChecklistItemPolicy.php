<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentChecklistItemPolicy
{
    /**
     * Determine whether the user can view checklist items for the student.
     *
     * Access is granted if:
     * 1. The authenticated user IS the student (user_id match), OR
     * 2. The authenticated user is an instructor who owns the student (instructor_id match)
     */
    public function viewAny(User $user, Student $student): bool
    {
        return $this->canAccessStudentChecklist($user, $student);
    }

    /**
     * Determine whether the user can update a checklist item for the student.
     *
     * Same access rules as viewing.
     */
    public function update(User $user, Student $student): bool
    {
        return $this->canAccessStudentChecklist($user, $student);
    }

    /**
     * Check whether the user can access the student's checklist items.
     */
    private function canAccessStudentChecklist(User $user, Student $student): bool
    {
        if ($user->isStudent() && $student->user_id === $user->id) {
            return true;
        }

        if ($user->isInstructor() && $student->instructor_id === $user->instructor?->id) {
            return true;
        }

        return false;
    }
}
