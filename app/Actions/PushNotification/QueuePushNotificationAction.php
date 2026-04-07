<?php

declare(strict_types=1);

namespace App\Actions\PushNotification;

use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;
use App\Models\User;

class QueuePushNotificationAction
{
    public function __invoke(User $user, string $title, string $body, ?array $data = null): PushNotification
    {
        return PushNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => PushNotificationStatus::PENDING,
        ]);
    }
}
