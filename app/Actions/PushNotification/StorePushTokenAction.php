<?php

declare(strict_types=1);

namespace App\Actions\PushNotification;

use App\Models\User;

class StorePushTokenAction
{
    public function __invoke(User $user, string $token): User
    {
        $user->update(['expo_push_token' => $token]);

        return $user;
    }
}
