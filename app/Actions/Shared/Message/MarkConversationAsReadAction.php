<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Models\Message;
use App\Models\User;

class MarkConversationAsReadAction
{
    /**
     * Mark every unread message sent by $otherUser to $reader as read.
     *
     * Idempotent bulk update — messages already read keep their original
     * read_at timestamp. Returns the number of messages newly marked read.
     */
    public function __invoke(User $reader, User $otherUser): int
    {
        return Message::query()
            ->where('to', $reader->id)
            ->where('from', $otherUser->id)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
