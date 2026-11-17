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
     * Rows younger than 15s are skipped: PushNotificationService::sendNowIfHasToken
     * creates its row as pending and is still sending it inline (Expo call
     * capped at 5s), so picking it up here would deliver it twice.
     *
     * @return array{sent: int, failed: int}
     */
    public function __invoke(): array
    {
        $pending = PushNotification::query()
            ->where('status', PushNotificationStatus::PENDING)
            ->where('created_at', '<=', now()->subSeconds(15))
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
