<?php

declare(strict_types=1);

namespace App\Actions\PushNotification;

use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemovePushTokenAction
{
    /**
     * Clear the user's Expo push token, but only when it matches the token the
     * device sent — the last device to register wins, so a stale device must
     * not switch off pushes for the user's current one. When cleared, any
     * pending (unsent) notifications are cancelled so the next
     * `push:send-queued` run does not deliver them.
     *
     * @return bool Whether the token matched and was removed
     */
    public function __invoke(User $user, string $token): bool
    {
        if ($user->expo_push_token !== $token) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            $user->update(['expo_push_token' => null]);

            PushNotification::query()
                ->where('user_id', $user->id)
                ->where('status', PushNotificationStatus::PENDING)
                ->update([
                    'status' => PushNotificationStatus::CANCELLED,
                    'error_message' => 'Push token removed by user.',
                ]);
        });

        return true;
    }
}
