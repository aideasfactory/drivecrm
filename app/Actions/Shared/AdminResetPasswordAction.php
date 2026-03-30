<?php

declare(strict_types=1);

namespace App\Actions\Shared;

use App\Models\User;

class AdminResetPasswordAction
{
    /**
     * Reset a user's password from the admin side.
     * No current password check — this is an admin-level operation.
     */
    public function __invoke(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
        ]);

        (new LogActivityAction)(
            $user->instructor ?? $user->student,
            'Password was reset by an administrator',
            'account'
        );
    }
}
