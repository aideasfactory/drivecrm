<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Instructor;
use App\Models\User;

class InstructorPolicy
{
    /**
     * Determine whether the user can update the instructor profile.
     *
     * Access is granted only if the authenticated user IS the instructor (user_id match).
     */
    public function update(User $user, Instructor $instructor): bool
    {
        return $user->isInstructor() && $instructor->user_id === $user->id;
    }
}
