<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class MessagePolicy
{
    /**
     * Determine whether the user can view their conversation list.
     *
     * Any authenticated instructor or student can view their own conversations.
     */
    public function viewAny(User $user): bool
    {
        return $user->isInstructor() || $user->isStudent();
    }

    /**
     * Determine whether the user can view a conversation with another user.
     *
     * Access is granted if the two users have an instructor-student relationship:
     * 1. Authenticated instructor viewing conversation with their student, OR
     * 2. Authenticated student viewing conversation with their assigned instructor.
     */
    public function viewConversation(User $user, User $otherUser): bool
    {
        return $this->hasMessagingRelationship($user, $otherUser);
    }

    /**
     * Determine whether the user can send a message to another user.
     *
     * Same rule as viewing: users can only message those they have
     * an instructor-student relationship with.
     */
    public function send(User $user, User $otherUser): bool
    {
        return $this->hasMessagingRelationship($user, $otherUser);
    }

    /**
     * Check if two users have a valid messaging relationship.
     *
     * Valid relationships:
     * - Either user is an owner (admin) — supports the support channel
     *   where any instructor or student can message the admin directly
     * - User A is an instructor and User B is their student
     * - User A is a student and User B is their instructor
     */
    private function hasMessagingRelationship(User $userA, User $userB): bool
    {
        if ($userA->isOwner() || $userB->isOwner()) {
            return true;
        }

        if ($userA->isInstructor() && $userB->isStudent()) {
            return $userB->student?->instructor_id === $userA->instructor?->id;
        }

        if ($userA->isStudent() && $userB->isInstructor()) {
            return $userA->student?->instructor_id === $userB->instructor?->id;
        }

        return false;
    }
}
