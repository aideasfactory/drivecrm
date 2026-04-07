<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendQueuedPushNotifications extends Command
{
    protected $signature = 'push:send-queued';

    protected $description = 'Process and send all pending push notifications';

    public function handle(PushNotificationService $pushNotificationService): int
    {
        $this->info('Processing queued push notifications...');

        $result = $pushNotificationService->processPending();

        if ($result['sent'] === 0 && $result['failed'] === 0) {
            $this->info('No pending push notifications to process.');

            return 0;
        }

        $this->info("Summary: {$result['sent']} sent, {$result['failed']} failed.");

        return $result['failed'] > 0 ? 1 : 0;
    }
}
