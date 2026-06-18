<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Support\ArchiveConversationAction;
use App\Actions\Support\ReopenConversationAction;
use App\Models\Message;
use App\Models\SupportTicketArchive;
use App\Models\User;
use Illuminate\Support\Collection;

class SupportTicketService extends BaseService
{
    public function __construct(
        protected MessageService $messageService,
        protected ArchiveConversationAction $archiveConversation,
        protected ReopenConversationAction $reopenConversation
    ) {}

    /**
     * Get the owner's conversations annotated with an `is_archived` flag.
     *
     * A conversation counts as archived only while its archive record's
     * `archived_at` is at or after the latest message — so a newer message
     * from the participant automatically reopens the ticket.
     *
     * @return Collection<int, array{user: User, latest_message: Message, is_archived: bool}>
     */
    public function getAnnotatedConversations(User $owner): Collection
    {
        $conversations = $this->messageService->getConversations($owner);

        $archives = SupportTicketArchive::query()
            ->where('owner_id', $owner->id)
            ->get()
            ->keyBy('participant_id');

        return $conversations->map(function (array $conversation) use ($archives): array {
            $participantId = $conversation['user']->id;
            $archive = $archives->get($participantId);
            $latestAt = $conversation['latest_message']->created_at;

            $conversation['is_archived'] = $archive !== null
                && $latestAt !== null
                && $archive->archived_at->greaterThanOrEqualTo($latestAt);

            return $conversation;
        })->values();
    }

    /**
     * Whether the conversation with the given participant is currently archived.
     */
    public function isArchived(User $owner, User $participant): bool
    {
        return $this->getAnnotatedConversations($owner)
            ->firstWhere(fn (array $c): bool => $c['user']->id === $participant->id)['is_archived'] ?? false;
    }

    /**
     * Archive (close) the conversation with a participant.
     */
    public function archive(User $owner, User $participant): void
    {
        ($this->archiveConversation)($owner, $participant);
    }

    /**
     * Reopen (un-archive) the conversation with a participant.
     */
    public function reopen(User $owner, User $participant): void
    {
        ($this->reopenConversation)($owner, $participant);
    }
}
