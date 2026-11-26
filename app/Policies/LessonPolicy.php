<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Lesson;
use App\Models\Student;
use App\Models\User;

class LessonPolicy
{
    /**
     * Determine whether the user can view lessons for the given student.
     *
     * Access is granted if:
     * 1. The authenticated user IS the student (user_id match), OR
     * 2. The authenticated user is an instructor linked to the student (instructor_id match)
     */
    public function viewAny(User $user, Student $student): bool
    {
        return $this->canAccessStudentLessons($user, $student);
    }

    /**
     * Determine whether the user can view a specific lesson for the given student.
     *
     * Same access rules as viewAny, plus the instructor who taught the lesson
     * (a pupil transferred to someone else keeps the old instructor's past
     * lessons on that instructor's diary). The lesson's ownership to the
     * student is enforced at the query level (scoped via student's orders).
     */
    public function view(User $user, Student $student, ?Lesson $lesson = null): bool
    {
        return $this->canAccessStudentLessons($user, $student)
            || $this->isLessonsInstructor($user, $lesson);
    }

    /**
     * Determine whether the user can sign off a lesson for the given student.
     *
     * The student's assigned instructor, or the instructor the lesson belongs
     * to — so a lesson taught before the pupil was transferred can still be
     * signed off (and paid out) by the instructor who taught it.
     */
    public function signOff(User $user, Student $student, ?Lesson $lesson = null): bool
    {
        return $this->isStudentsInstructor($user, $student)
            || $this->isLessonsInstructor($user, $lesson);
    }

    /**
     * Determine whether the user can assign resources to a lesson.
     *
     * Only the student's assigned instructor can assign resources.
     */
    public function assignResources(User $user, Student $student): bool
    {
        return $this->isStudentsInstructor($user, $student);
    }

    /**
     * Check if the user is the student's assigned instructor.
     */
    private function isStudentsInstructor(User $user, Student $student): bool
    {
        return $user->isInstructor() && $student->instructor_id === $user->instructor?->id;
    }

    /**
     * Check if the user is the instructor the lesson itself belongs to.
     */
    private function isLessonsInstructor(User $user, ?Lesson $lesson): bool
    {
        return $lesson !== null
            && $user->isInstructor()
            && $user->instructor !== null
            && $lesson->instructor_id === $user->instructor->id;
    }

    /**
     * Check if the user can access the student's lessons.
     */
    private function canAccessStudentLessons(User $user, Student $student): bool
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
