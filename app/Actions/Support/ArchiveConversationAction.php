<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Models\SupportTicketArchive;
use App\Models\User;
use Illuminate\Support\Facades\Date;

class ArchiveConversationAction
{
    /**
     * Archive (close) the conversation between an owner and a participant.
     *
     * Stamps `archived_at = now()` so the ticket stays archived only while no
     * newer message has arrived — a later message auto-reopens it to the inbox.
     */
    public function __invoke(User $owner, User $participant): SupportTicketArchive
    {
        return SupportTicketArchive::updateOrCreate(
            [
                'owner_id' => $owner->id,
                'participant_id' => $participant->id,
            ],
            [
                'archived_at' => Date::now(),
            ],
        );
    }
}
