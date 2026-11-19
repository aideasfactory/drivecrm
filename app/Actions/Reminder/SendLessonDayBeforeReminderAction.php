<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Actions\Shared\LogActivityAction;
use App\Enums\LessonStatus;
use App\Enums\ReminderType;
use App\Models\Lesson;
use App\Models\LessonReminder;
use App\Models\PushNotification;
use App\Services\PushNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SendLessonDayBeforeReminderAction
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Queue the "lesson tomorrow" push to the lesson's learner and record the
     * reminder, then log the activity. Delivery is left to `push:send-queued`.
     *
     * The lesson row is locked and re-checked inside one transaction, and the
     * reminder row and push row are written together, so a concurrent or
     * repeated run can never queue the same reminder twice, and a failure
     * leaves nothing recorded so the next run retries it.
     *
     * Returns false when the lesson no longer qualifies (cancelled, moved,
     * token removed or already reminded since the candidate list was built).
     */
    public function __invoke(Lesson $lesson): bool
    {
        $pushNotification = DB::transaction(function () use ($lesson): ?PushNotification {
            $locked = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->first();

            if (! $locked
                || $locked->status !== LessonStatus::PENDING
                || ! $locked->date
                || ! $locked->start_time
                || $locked->date->toDateString() !== $lesson->date?->toDateString()) {
                return null;
            }

            $alreadyReminded = $locked->reminders()
                ->where('type', ReminderType::LESSON_DAY_BEFORE->value)
                ->where('sent_at', '>=', GetLessonDayBeforeRemindersDueAction::remindedSince($locked->date->toDateString()))
                ->exists();

            $user = $lesson->order?->student?->user;

            if ($alreadyReminded || ! $user?->expo_push_token) {
                return null;
            }

            LessonReminder::updateOrCreate(
                ['lesson_id' => $locked->id, 'type' => ReminderType::LESSON_DAY_BEFORE->value],
                ['sent_at' => CarbonImmutable::now()],
            );

            $instructorName = $lesson->instructor?->user?->name ?: $lesson->order->instructor?->user?->name;
            $pickup = $lesson->order->student->pickupLocationLine();

            return $this->pushNotificationService->queue(
                $user,
                $locked->is_practical_test ? 'Driving test tomorrow' : 'Driving lesson tomorrow',
                $this->body($locked, $instructorName, $pickup),
                $this->data($locked, $lesson->order->student->id, $instructorName, $pickup),
            );
        });

        if (! $pushNotification) {
            return false;
        }

        $student = $lesson->order->student;
        $lessonDate = $lesson->date->format('d M Y');
        $lessonTime = $lesson->start_time->format('H:i');

        ($this->logActivity)(
            $student,
            "Lesson reminder push notification queued for user #{$pushNotification->user_id} for lesson on {$lessonDate} at {$lessonTime}",
            'notification',
            [
                'type' => ReminderType::LESSON_DAY_BEFORE->value,
                'lesson_id' => $lesson->id,
                'push_notification_id' => $pushNotification->id,
            ],
            'Lesson reminder push notification sent',
        );

        return true;
    }

    /**
     * e.g. "Your lesson with Sam Taylor is tomorrow, Friday 20 November, at
     * 10:00. Pickup: 1 High Street, AB1 2CD". Instructor and pickup are left
     * out when unknown.
     */
    protected function body(Lesson $lesson, ?string $instructorName, ?string $pickup): string
    {
        return ($lesson->is_practical_test ? 'Your driving test' : 'Your lesson')
            .($instructorName ? " with {$instructorName}" : '')
            .' is tomorrow, '.$lesson->date->format('l j F')
            .', at '.$lesson->start_time->format('H:i').'.'
            .($pickup ? " Pickup: {$pickup}" : '');
    }

    /**
     * @return array{type: string, lesson_id: int, student_id: int, date: string, start_time: string, end_time: string|null, is_practical_test: bool, instructor_name: string|null, pickup: string|null}
     */
    protected function data(Lesson $lesson, int $studentId, ?string $instructorName, ?string $pickup): array
    {
        return [
            'type' => 'lesson_reminder',
            'lesson_id' => $lesson->id,
            'student_id' => $studentId,
            'date' => $lesson->date->toDateString(),
            'start_time' => $lesson->start_time->format('H:i'),
            'end_time' => $lesson->end_time?->format('H:i'),
            'is_practical_test' => (bool) $lesson->is_practical_test,
            'instructor_name' => $instructorName,
            'pickup' => $pickup,
        ];
    }
}
