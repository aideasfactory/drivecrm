<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Actions\Shared\LogActivityAction;
use App\Enums\ReminderType;
use App\Models\Lesson;
use App\Models\LessonReminder;
use App\Services\PushNotificationService;
use App\Support\BookingPayments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendLessonDayBeforeReminderAction
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Queue the day-before lesson reminder push to the learner's user and
     * record it, so it is sent at most once per lesson date.
     *
     * The reminder row and the queued push are written in one transaction:
     * either both exist or neither does, so a failure is retried on the next
     * `reminders:send` run and a success is never repeated (the row is locked,
     * and the unique `(lesson_id, type)` key stops a concurrent run). Failures
     * are logged and swallowed so one lesson cannot stop the rest of the batch.
     *
     * @return bool Whether a push was queued
     */
    public function __invoke(Lesson $lesson): bool
    {
        $order = $lesson->order;
        $student = $order?->student;
        $user = $student?->user;

        if (! $order || ! $student || ! $user?->expo_push_token || ! $lesson->date || ! $lesson->start_time) {
            return false;
        }

        $instructorName = $lesson->instructor?->user?->name ?? $order->instructor?->user?->name;
        $pickup = $order->pickupLocationLine();
        $lessonDateLabel = $lesson->date->format('l j F');
        $startTime = $lesson->start_time->format('H:i');

        $title = $lesson->is_practical_test ? 'Your driving test is tomorrow' : 'Your driving lesson is tomorrow';
        $body = $lessonDateLabel.' at '.$startTime
            .($instructorName ? ' with '.$instructorName : '')
            .'.'
            .($pickup ? ' Pickup: '.$pickup.'.' : '');

        $data = [
            'type' => ReminderType::LESSON_DAY_BEFORE->value,
            'lesson_id' => $lesson->id,
            'student_id' => $student->id,
            'order_id' => $order->id,
            'lesson_date' => $lesson->date->toDateString(),
            'start_time' => $startTime,
            'end_time' => $lesson->end_time?->format('H:i'),
            'instructor_name' => $instructorName,
            'pickup' => $pickup,
            'is_practical_test' => (bool) $lesson->is_practical_test,
        ];

        try {
            $queued = DB::transaction(function () use ($lesson, $user, $title, $body, $data): bool {
                $now = CarbonImmutable::now();
                $startOfUkDay = GetLessonDayBeforeRemindersDueAction::startOfUkDay(CarbonImmutable::now(BookingPayments::timezone()));

                $reminder = LessonReminder::query()
                    ->where('lesson_id', $lesson->id)
                    ->where('type', ReminderType::LESSON_DAY_BEFORE->value)
                    ->lockForUpdate()
                    ->first();

                if ($reminder?->sent_at !== null && $reminder->sent_at->gte($startOfUkDay)) {
                    return false;
                }

                if ($reminder) {
                    $reminder->update(['sent_at' => $now]);
                } else {
                    LessonReminder::create([
                        'lesson_id' => $lesson->id,
                        'type' => ReminderType::LESSON_DAY_BEFORE->value,
                        'sent_at' => $now,
                    ]);
                }

                $this->pushNotificationService->queue($user, $title, $body, $data);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to queue day-before lesson reminder', [
                'lesson_id' => $lesson->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $queued) {
            return false;
        }

        try {
            ($this->logActivity)(
                $student,
                'Lesson reminder push sent for '.$lessonDateLabel.' at '.$startTime,
                'notification',
                ['type' => ReminderType::LESSON_DAY_BEFORE->value, 'lesson_id' => $lesson->id],
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to log day-before lesson reminder activity', [
                'lesson_id' => $lesson->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
