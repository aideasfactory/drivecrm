<?php

declare(strict_types=1);

namespace App\Actions\PushNotification;

use App\Enums\PushNotificationStatus;
use App\Models\PushNotification;

class ProcessPendingNotificationsAction
{
    public function __construct(
        protected SendPushNotificationAction $sendPushNotification,
    ) {}

    /**
     * @return array{sent: int, failed: int}
     */
    public function __invoke(): array
    {
        $pending = PushNotification::query()
            ->where('status', PushNotificationStatus::PENDING)
            ->with('user')
            ->oldest()
            ->limit(100)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($pending as $notification) {
            $success = ($this->sendPushNotification)($notification);

            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
