<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;

class SendMessageAction
{
    /**
     * Send a direct message from one user to another.
     *
     * Only creates the row — MessageObserver fires the notification pipeline
     * (email, push, activity log) for every created message, so nothing else
     * must be dispatched here.
     */
    public function __invoke(User $sender, User $recipient, string $messageText): Message
    {
        return Message::create([
            'from' => $sender->id,
            'to' => $recipient->id,
            'message' => $messageText,
            'type' => MessageType::DIRECT,
        ]);
    }
}
