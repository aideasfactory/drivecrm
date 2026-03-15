<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

class LogoutAction
{
    /**
     * Revoke the current access token for the authenticated user.
     */
    public function __invoke(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
