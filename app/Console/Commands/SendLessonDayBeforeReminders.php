<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reminder\GetLessonDayBeforeRemindersDueAction;
use App\Services\LessonReminderService;
use Illuminate\Console\Command;

class SendLessonDayBeforeReminders extends Command
{
    protected $signature = 'reminders:lesson-day-before';

    protected $description = 'Queue day-before lesson reminder pushes to learners (UK evening send window)';

    public function handle(LessonReminderService $lessonReminderService): int
    {
        $queued = $lessonReminderService->dispatchDayBeforeReminders();

        $this->info(sprintf(
            'Queued %d lesson reminder push(es). Send window: %s-%s UK time.',
            $queued,
            GetLessonDayBeforeRemindersDueAction::SEND_WINDOW_START,
            GetLessonDayBeforeRemindersDueAction::SEND_WINDOW_END,
        ));

        return self::SUCCESS;
    }
}
