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
     * When $otherUser is an owner (admin), messages from every owner are
     * marked — see Message::scopeSentBetween().
     */
    public function __invoke(User $reader, User $otherUser): int
    {
        return Message::query()
            ->sentBetween($otherUser, $reader)
            ->unread()
            ->update(['read_at' => now()]);
    }
}
