<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\User;

class MarkSupportConversationReadAction
{
    /**
     * Mark every unread message the participant sent to any owner (admin) as read.
     *
     * Idempotent — already-read messages keep their original read_at.
     * Returns the number of messages newly marked read.
     */
    public function __invoke(User $participant): int
    {
        return Message::query()
            ->where('from', $participant->id)
            ->whereIn('to', User::query()->select('id')->where('role', UserRole::OWNER))
            ->unread()
            ->update(['read_at' => now()]);
    }
}
