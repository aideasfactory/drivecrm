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
     * 2. The authenticated user is an instructor linked to the student (instructor_id match), OR
     * 3. The authenticated user is a previous instructor (e.g. before a transfer)
     *    with a lesson they taught still to sign off — the controller then
     *    limits the list to the lessons they taught.
     */
    public function viewAny(User $user, Student $student): bool
    {
        return $this->canAccessStudentLessons($user, $student)
            || $this->isPreviousInstructorWithSignOffDue($user, $student);
    }

    /**
     * Determine whether the user only sees the lessons they taught for this
     * student: a previous instructor, not the student or current instructor.
     */
    public function viewTaughtLessonsOnly(User $user, Student $student): bool
    {
        return ! $this->canAccessStudentLessons($user, $student)
            && $this->isPreviousInstructorWithSignOffDue($user, $student);
    }

    /**
     * Determine whether the user can view a specific lesson for the given student.
     *
     * Same access rules as viewAny, plus the instructor who taught the lesson,
     * so an instructor keeps access to lessons of a pupil transferred away.
     * The lesson's ownership to the student is enforced at the query level
     * (scoped via student's orders).
     */
    public function view(User $user, Student $student, ?Lesson $lesson = null): bool
    {
        return $this->canAccessStudentLessons($user, $student)
            || ($lesson !== null && $this->taughtLesson($user, $lesson));
    }

    /**
     * Determine whether the user can sign off a lesson for the given student.
     *
     * Only the student's assigned instructor can sign off lessons.
     */
    public function signOff(User $user, Student $student): bool
    {
        return $this->isStudentsInstructor($user, $student);
    }

    /**
     * Determine whether the user can sign off this specific lesson.
     *
     * Only the instructor who taught it (`lessons.instructor_id`), who is also
     * the instructor the payout goes to. A transfer only moves future lessons,
     * so the previous instructor keeps signing off the lessons he taught and
     * the pupil's new instructor cannot sign them off.
     */
    public function signOffLesson(User $user, Lesson $lesson): bool
    {
        return $this->taughtLesson($user, $lesson);
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
     * Check if the user is the instructor assigned to the lesson.
     */
    private function taughtLesson(User $user, Lesson $lesson): bool
    {
        return $user->isInstructor()
            && $lesson->instructor_id !== null
            && $lesson->instructor_id === $user->instructor?->id;
    }

    /**
     * Check if the user is a previous instructor of the student with a lesson
     * they taught still waiting for sign-off.
     */
    private function isPreviousInstructorWithSignOffDue(User $user, Student $student): bool
    {
        return $user->isInstructor()
            && $student->hasSignOffDueForPreviousInstructor($user->instructor);
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
