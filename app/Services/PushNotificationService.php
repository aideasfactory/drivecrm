<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\PushNotification\ProcessPendingNotificationsAction;
use App\Actions\PushNotification\QueuePushNotificationAction;
use App\Actions\PushNotification\SendPushNotificationAction;
use App\Actions\PushNotification\StorePushTokenAction;
use App\Models\PushNotification;
use App\Models\User;

class PushNotificationService extends BaseService
{
    public function __construct(
        protected StorePushTokenAction $storePushToken,
        protected QueuePushNotificationAction $queuePushNotification,
        protected SendPushNotificationAction $sendPushNotification,
        protected ProcessPendingNotificationsAction $processPendingNotifications,
    ) {}

    public function storeToken(User $user, string $token): User
    {
        return ($this->storePushToken)($user, $token);
    }

    public function queue(User $user, string $title, string $body, ?array $data = null): PushNotification
    {
        return ($this->queuePushNotification)($user, $title, $body, $data);
    }

    public function send(PushNotification $notification): bool
    {
        return ($this->sendPushNotification)($notification);
    }

    /**
     * Queue a notification and send it immediately.
     */
    public function queueAndSend(User $user, string $title, string $body, ?array $data = null): PushNotification
    {
        $notification = ($this->queuePushNotification)($user, $title, $body, $data);
        ($this->sendPushNotification)($notification);

        return $notification->fresh();
    }

    /**
     * @return array{sent: int, failed: int}
     */
    public function processPending(): array
    {
        return ($this->processPendingNotifications)();
    }
}
