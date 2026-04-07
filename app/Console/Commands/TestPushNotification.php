<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    protected $signature = 'push:test {user_id : The ID of the user to send a test notification to} {--title=Test Notification : The notification title} {--body=This is a test push notification from Drive. : The notification body}';

    protected $description = 'Send a test push notification to a specific user';

    public function handle(PushNotificationService $pushNotificationService): int
    {
        $user = User::find($this->argument('user_id'));

        if (! $user) {
            $this->error("User with ID {$this->argument('user_id')} not found.");

            return 1;
        }

        if (! $user->expo_push_token) {
            $this->error("User '{$user->name}' does not have a push token registered.");

            return 1;
        }

        $this->info("Sending test push notification to {$user->name} ({$user->email})...");

        $notification = $pushNotificationService->queueAndSend(
            $user,
            $this->option('title'),
            $this->option('body'),
        );

        if ($notification->status->value === 'sent') {
            $this->info('Push notification sent successfully.');

            return 0;
        }

        $this->error("Push notification failed: {$notification->error_message}");

        return 1;
    }
}
