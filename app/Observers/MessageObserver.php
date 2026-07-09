<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Shared\LogActivityAction;
use App\Enums\MessageType;
use App\Models\Lesson;
use App\Models\Message;
use App\Notifications\InstructorArrivedNotification;
use App\Notifications\InstructorOnWayNotification;
use App\Notifications\NewMessageNotification;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The single notification pipeline for the messages table: every created row
 * fires the appropriate email, push notification, and activity log here, so
 * call sites only ever create the row. Creating a Message IS sending it —
 * senders must not dispatch their own comms or they will duplicate.
 */
class MessageObserver
{
    public function __construct(
        protected LogActivityAction $logActivity,
        protected PushNotificationService $pushNotificationService,
    ) {}

    public function created(Message $message): void
    {
        match ($message->type) {
            MessageType::LESSON_ON_WAY, MessageType::LESSON_ARRIVED => $this->handleLessonStatus($message),
            MessageType::BROADCAST => $this->handleBroadcast($message),
            default => $this->handleDirect($message),
        };
    }

    /**
     * Generic direct message: email + push to the recipient, and activity log
     * entries for the instructor/student pair when the participants resolve to
     * one (admin/support participants have neither profile, so no entries).
     */
    protected function handleDirect(Message $message): void
    {
        $sender = $message->sender;
        $recipient = $message->recipient;

        if (! $sender || ! $recipient) {
            return;
        }

        $this->notifyRecipient($message);

        $instructor = $sender->instructor ?? $recipient->instructor;
        $student = $sender->student ?? $recipient->student;

        if ($instructor && $student) {
            $truncated = Str::limit($message->message, 100);

            ($this->logActivity)(
                $student,
                'Message sent to '.$student->first_name.': '.$truncated,
                'message',
                null,
                $truncated
            );

            ($this->logActivity)(
                $instructor,
                'Message sent to '.$student->first_name.' '.$student->surname.': '.$truncated,
                'message',
                null,
                $student->first_name.' '.$student->surname.': '.$truncated
            );
        }
    }

    /**
     * Broadcast fan-out row: same email + push as a direct message, but no
     * per-row activity log — the broadcast caller writes a single summary
     * entry instead of one entry per recipient.
     */
    protected function handleBroadcast(Message $message): void
    {
        if ($message->sender && $message->recipient) {
            $this->notifyRecipient($message);
        }
    }

    /**
     * Email + push for a generic message, addressed to the recipient user.
     */
    protected function notifyRecipient(Message $message): void
    {
        $message->recipient->notify(new NewMessageNotification($message, $message->sender));

        $this->pushNotificationService->queueIfHasToken(
            $message->recipient,
            'New message from '.($message->sender->name ?: 'Someone'),
            Str::limit($message->message, 140),
            [
                'type' => 'message',
                'message_id' => $message->id,
                'from_user_id' => $message->sender->id,
            ]
        );
    }

    /**
     * Lesson status update (on way / arrived): lesson-specific email routed to
     * the learner's email (falling back to contact email), push with the
     * message body as copy, and an activity log entry on the instructor's
     * timeline. Context comes from `meta.lesson_id` plus the sender's
     * instructor profile and the recipient's student profile.
     */
    protected function handleLessonStatus(Message $message): void
    {
        $onWay = $message->type === MessageType::LESSON_ON_WAY;
        $lesson = Lesson::find($message->meta['lesson_id'] ?? null);
        $instructor = $message->sender?->instructor;
        $student = $message->recipient?->student;

        if (! $lesson || ! $instructor || ! $student) {
            return;
        }

        $recipientEmail = $student->email ?: $student->contact_email;

        if ($recipientEmail) {
            Notification::route('mail', $recipientEmail)->notify(
                $onWay
                    ? new InstructorOnWayNotification($lesson, $instructor, $student)
                    : new InstructorArrivedNotification($lesson, $instructor, $student)
            );
        }

        $this->pushNotificationService->queueIfHasToken(
            $message->recipient,
            $onWay ? 'Your instructor is on the way' : 'Your instructor has arrived',
            $message->message,
            ['lesson_id' => $lesson->id, 'notification_type' => $onWay ? 'on_way' : 'arrived'],
        );

        ($this->logActivity)(
            $instructor,
            ($onWay ? 'Instructor is on their way to lesson #' : 'Instructor has arrived for lesson #')
                .$lesson->id.' with '.$student->first_name.' '.$student->surname,
            'lesson',
            [
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
                'notification_type' => $onWay ? 'on_way' : 'arrived',
            ]
        );
    }
}
