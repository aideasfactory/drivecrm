<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Reminder\GetMilesEndRemindersDueAction;
use App\Actions\Reminder\GetMilesStartRemindersDueAction;
use App\Actions\Reminder\GetPaymentRemindersDueAction;
use App\Actions\Reminder\SendMilesReminderAction;
use App\Actions\Reminder\SendPaymentReminderAction;
use App\Enums\ReminderType;
use App\Models\Lesson;

class ReminderService extends BaseService
{
    public function __construct(
        protected GetMilesStartRemindersDueAction $getMilesStartReminders,
        protected GetMilesEndRemindersDueAction $getMilesEndReminders,
        protected GetPaymentRemindersDueAction $getPaymentReminders,
        protected SendMilesReminderAction $sendMilesReminder,
        protected SendPaymentReminderAction $sendPaymentReminder,
    ) {}

    /**
     * Dispatch "miles start" reminders for each instructor's first lesson today.
     */
    public function dispatchMilesStartReminders(): int
    {
        $lessons = ($this->getMilesStartReminders)();

        $lessons->each(fn (Lesson $lesson) => ($this->sendMilesReminder)($lesson, ReminderType::MILES_START));

        return $lessons->count();
    }

    /**
     * Dispatch "miles end" reminders for each instructor's last lesson today.
     */
    public function dispatchMilesEndReminders(): int
    {
        $lessons = ($this->getMilesEndReminders)();

        $lessons->each(fn (Lesson $lesson) => ($this->sendMilesReminder)($lesson, ReminderType::MILES_END));

        return $lessons->count();
    }

    /**
     * Dispatch 48-hour payment-due reminders.
     */
    public function dispatchPaymentDueReminders(): int
    {
        $lessons = ($this->getPaymentReminders)();

        $lessons->each(fn (Lesson $lesson) => ($this->sendPaymentReminder)($lesson));

        return $lessons->count();
    }

    /**
     * Dispatch all reminder types.
     *
     * @return array{miles_start: int, miles_end: int, payment_due: int}
     */
    public function dispatchAll(): array
    {
        return [
            'miles_start' => $this->dispatchMilesStartReminders(),
            'miles_end' => $this->dispatchMilesEndReminders(),
            'payment_due' => $this->dispatchPaymentDueReminders(),
        ];
    }
}
