<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordAction
{
    /**
     * Change the user's password and clear the password_change_required flag.
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }

        $user->update([
            'password' => $newPassword,
            'password_change_required' => false,
        ]);
    }
}
