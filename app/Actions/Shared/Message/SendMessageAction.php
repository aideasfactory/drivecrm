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
     * Creates the message record, logs activity on both student and instructor
     * entities, and dispatches an email notification to the recipient.
     *
     * @param  User  $sender  The user sending the message
     * @param  User  $recipient  The user receiving the message
     * @param  string  $messageText  The message content
     * @param  Student  $student  The student entity (for activity logging)
     * @param  Instructor  $instructor  The instructor entity (for activity logging)
     */
    public function __invoke(
        User $sender,
        User $recipient,
        string $messageText,
        Student $student,
        Instructor $instructor
    ): Message {
        $message = Message::create([
            'from' => $sender->id,
            'to' => $recipient->id,
            'message' => $messageText,
        ]);

        $truncated = Str::limit($messageText, 100);

        // Log activity on student entity
        ($this->logActivity)(
            $student,
            'Message sent to '.$student->first_name.': '.$truncated,
            'message'
        );

        // Log activity on instructor entity
        ($this->logActivity)(
            $instructor,
            'Message sent to '.$student->first_name.' '.$student->surname.': '.$truncated,
            'message'
        );

        // Send email notification to recipient
        $recipient->notify(new NewMessageNotification($message, $sender));

        return $message;
    }
}
