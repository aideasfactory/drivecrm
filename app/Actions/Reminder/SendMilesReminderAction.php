<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Actions\Shared\LogActivityAction;
use App\Enums\ReminderType;
use App\Models\Lesson;
use App\Models\LessonReminder;
use App\Services\PushNotificationService;
use Carbon\CarbonImmutable;

class SendMilesReminderAction
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Queue a mileage reminder push to the lesson's instructor, log the
     * activity, and record the reminder so it is never sent twice.
     */
    public function __invoke(Lesson $lesson, ReminderType $type): void
    {
        $instructor = $lesson->instructor;
        $user = $instructor?->user;

        // Guard (per locked decision): only push to a genuine instructor who has
        // a registered Expo push token. No token == push not enabled.
        if (! $user || ! $user->isInstructor() || ! $user->expo_push_token) {
            return;
        }

        $this->pushNotificationService->queueIfHasToken(
            $user,
            'Mileage reminder',
            'Remember to input your miles',
            ['type' => $type->value, 'lesson_id' => $lesson->id],
        );

        ($this->logActivity)(
            $instructor,
            'Miles reminder ('.$type->value.') sent',
            'notification',
            ['type' => $type->value, 'lesson_id' => $lesson->id],
        );

        LessonReminder::updateOrCreate(
            ['lesson_id' => $lesson->id, 'type' => $type->value],
            ['sent_at' => CarbonImmutable::now()],
        );
    }
}
