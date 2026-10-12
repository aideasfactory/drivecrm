<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Models\Instructor;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Services\PushNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotifyInstructorOfLessonPaidAction
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Push the instructor a "lesson paid" notification naming the pupil and
     * the lesson's date and time. Several payments at once (a pay-in-full
     * booking) are summarised in one push from the earliest lesson. No-ops
     * when the instructor has no Expo push token; failures are logged and
     * never thrown so they cannot affect payment confirmation.
     *
     * @param  Collection<int, LessonPayment>  $lessonPayments
     */
    public function __invoke(Collection $lessonPayments, ?Student $student, ?Instructor $instructor): void
    {
        $user = $instructor?->user;

        if (! $user || ! $user->isInstructor() || ! $user->expo_push_token || $lessonPayments->isEmpty()) {
            return;
        }

        try {
            $sortedPayments = $lessonPayments
                ->filter(fn (LessonPayment $payment): bool => $payment->lesson !== null)
                ->sortBy(fn (LessonPayment $payment): string => $payment->lesson->date->format('Y-m-d').' '.$payment->lesson->start_time?->format('H:i'))
                ->values();

            $firstLesson = $sortedPayments->first()?->lesson;

            if (! $firstLesson) {
                return;
            }

            $pupilName = trim(($student?->first_name ?? '').' '.($student?->surname ?? '')) ?: 'Your pupil';
            $when = $firstLesson->date->format('D j M');

            if ($firstLesson->start_time) {
                $when .= ' at '.$firstLesson->start_time->format('H:i');
            }

            $lessonCount = $sortedPayments->count();

            [$title, $body] = $lessonCount === 1
                ? ['Lesson paid', "{$pupilName} has paid for their lesson on {$when}."]
                : ['Lessons paid', "{$pupilName} has paid for {$lessonCount} lessons, starting {$when}."];

            $pushNotification = $this->pushNotificationService->queueIfHasToken($user, $title, $body, [
                'type' => 'lesson_paid',
                'lesson_id' => $firstLesson->id,
                'lesson_ids' => $sortedPayments->pluck('lesson_id')->all(),
                'lesson_payment_ids' => $sortedPayments->pluck('id')->all(),
                'student_id' => $student?->id,
            ]);

            if ($pushNotification) {
                ($this->logActivity)(
                    $instructor,
                    "Lesson paid push notification queued: {$body}",
                    'notification',
                    [
                        'type' => 'lesson_paid',
                        'lesson_payment_ids' => $sortedPayments->pluck('id')->all(),
                        'push_notification_id' => $pushNotification->id,
                    ],
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to notify instructor that a lesson was paid', [
                'instructor_id' => $instructor->id,
                'lesson_payment_ids' => $lessonPayments->pluck('id')->all(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
