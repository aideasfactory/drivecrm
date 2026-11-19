<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendLessonDayBeforeReminders extends Command
{
    protected $signature = 'reminders:lesson-day-before';

    protected $description = 'Queue "lesson tomorrow" push reminders to learners (only inside the UK evening send window)';

    public function handle(ReminderService $reminderService): int
    {
        $counts = $reminderService->dispatchLessonDayBeforeReminders();

        $this->info("Summary: queued: {$counts['queued']}, skipped: {$counts['skipped']}, failed: {$counts['failed']}.");

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
