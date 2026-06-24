<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send scheduled miles and payment-due reminder notifications';

    public function handle(ReminderService $reminderService): int
    {
        $this->info('Dispatching scheduled reminders...');

        $counts = $reminderService->dispatchAll();

        $this->info("Summary: miles_start: {$counts['miles_start']}, miles_end: {$counts['miles_end']}, payment_due: {$counts['payment_due']}.");

        return self::SUCCESS;
    }
}
