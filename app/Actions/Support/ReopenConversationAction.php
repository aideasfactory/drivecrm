<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\SupportTicketArchive;
use App\Models\User;

class ReopenConversationAction
{
    /**
     * Reopen (un-archive) a participant's support conversation for the whole
     * admin team by removing every archive record for that participant.
     */
    public function __invoke(User $participant): void
    {
        SupportTicketArchive::query()
            ->where('participant_id', $participant->id)
            ->delete();
    }
}
