<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\SupportTicketArchive;
use App\Models\User;

class ReopenConversationAction
{
    /**
     * Reopen (un-archive) the conversation between an owner and a participant
     * by removing its archive record.
     */
    public function __invoke(User $owner, User $participant): void
    {
        SupportTicketArchive::query()
            ->where('owner_id', $owner->id)
            ->where('participant_id', $participant->id)
            ->delete();
    }
}
