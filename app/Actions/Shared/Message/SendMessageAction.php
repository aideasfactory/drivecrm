<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Actions\Shared\LogActivityAction;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Support\Str;

class SendMessageAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Send a message from one user to another.
     *
     * Creates the message record and dispatches an email notification to the
     * recipient. If both $student and $instructor are supplied, the message
     * is logged to each of their activity feeds (admin DM / mobile DM case).
     * Pass null for support-channel messages where no student/instructor
     * scope applies.
     */
    public function __invoke(
        User $sender,
        User $recipient,
        string $messageText,
        ?Student $student = null,
        ?Instructor $instructor = null
    ): Message {
        $message = Message::create([
            'from' => $sender->id,
            'to' => $recipient->id,
            'message' => $messageText,
        ]);

        if ($student && $instructor) {
            $truncated = Str::limit($messageText, 100);

            ($this->logActivity)(
                $student,
                'Message sent to '.$student->first_name.': '.$truncated,
                'message'
            );

            ($this->logActivity)(
                $instructor,
                'Message sent to '.$student->first_name.' '.$student->surname.': '.$truncated,
                'message'
            );
        }

        $recipient->notify(new NewMessageNotification($message, $sender));

        return $message;
    }
}
