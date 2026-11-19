<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Reminder\GetLessonDayBeforeRemindersDueAction;
use App\Actions\Reminder\SendLessonDayBeforeReminderAction;
use App\Models\Lesson;

/**
 * Learner lesson reminders. Kept apart from ReminderService so it never
 * builds the Stripe client the payment reminders need: neither job can stop
 * the other.
 */
class LessonReminderService extends BaseService
{
    public function __construct(
        protected GetLessonDayBeforeRemindersDueAction $getLessonDayBeforeReminders,
        protected SendLessonDayBeforeReminderAction $sendLessonDayBeforeReminder,
    ) {}

    /**
     * Queue day-before lesson reminder pushes to learners (UK evening only).
     *
     * @return int How many pushes were queued
     */
    public function dispatchDayBeforeReminders(): int
    {
        return ($this->getLessonDayBeforeReminders)()
            ->filter(fn (Lesson $lesson): bool => ($this->sendLessonDayBeforeReminder)($lesson))
            ->count();
    }
}
