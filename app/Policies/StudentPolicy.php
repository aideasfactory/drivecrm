<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Determine whether the user can create a student record.
     *
     * Only authenticated instructors can create student records.
     */
    public function create(User $user): bool
    {
        return $user->isInstructor();
    }

    /**
     * Determine whether the user can view the student record.
     *
     * Access is granted if:
     * 1. The authenticated user IS the student (user_id match), OR
     * 2. The authenticated user is an instructor who owns the student (instructor_id match)
     */
    public function view(User $user, Student $student): bool
    {
        return $this->isStudentOrLinkedInstructor($user, $student);
    }

    /**
     * Determine whether the user can update the student record.
     *
     * Access is granted if:
     * 1. The authenticated user IS the student (user_id match), OR
     * 2. The authenticated user is an instructor who owns the student (instructor_id match)
     */
    public function update(User $user, Student $student): bool
    {
        return $this->isStudentOrLinkedInstructor($user, $student);
    }

    /**
     * Determine whether the user can delete the student record.
     *
     * Access is granted if:
     * 1. The authenticated user IS the student (user_id match), OR
     * 2. The authenticated user is an instructor who owns the student (instructor_id match)
     */
    public function delete(User $user, Student $student): bool
    {
        return $this->isStudentOrLinkedInstructor($user, $student);
    }

    /**
     * Determine whether staff can delete the learner profile (soft delete).
     *
     * Owners can delete any profile, including unassigned duplicates.
     * Instructors can delete only pupils assigned to them.
     */
    public function deleteProfile(User $user, Student $student): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        if ($user->isInstructor() && $student->instructor_id === $user->instructor?->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user is the student themselves or their linked instructor.
     */
    private function isStudentOrLinkedInstructor(User $user, Student $student): bool
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
