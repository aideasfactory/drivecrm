<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Support\ArchiveConversationAction;
use App\Actions\Support\CountUnreadSupportConversationsAction;
use App\Actions\Support\GetSupportConversationsAction;
use App\Actions\Support\GetSupportThreadAction;
use App\Actions\Support\MarkSupportConversationReadAction;
use App\Actions\Support\ReopenConversationAction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The support inbox is shared by every owner (admin): conversations between
 * any admin and an instructor/pupil appear for all admins, and closing,
 * reopening and reading a ticket applies team-wide.
 */
class SupportTicketService extends BaseService
{
    public function __construct(
        protected GetSupportConversationsAction $getSupportConversations,
        protected GetSupportThreadAction $getSupportThread,
        protected MarkSupportConversationReadAction $markSupportConversationRead,
        protected CountUnreadSupportConversationsAction $countUnreadSupportConversations,
        protected ArchiveConversationAction $archiveConversation,
        protected ReopenConversationAction $reopenConversation
    ) {}

    /**
     * Get the shared support conversations annotated with an `is_archived` flag.
     *
     * @return Collection<int, array{user: User, latest_message: Message, is_archived: bool}>
     */
    public function getAnnotatedConversations(): Collection
    {
        return ($this->getSupportConversations)();
    }

    /**
     * Get the latest messages between a participant and any admin, oldest first.
     *
     * @return Collection<int, Message>
     */
    public function getThread(User $participant, int $limit = 100): Collection
    {
        return ($this->getSupportThread)($participant, $limit);
    }

    /**
     * Mark the participant's unread messages to any admin as read.
     */
    public function markAsRead(User $participant): int
    {
        return ($this->markSupportConversationRead)($participant);
    }

    /**
     * Number of conversations with unread messages, for the sidebar badge.
     *
     * Not cached — the count must drop as soon as an admin opens a thread.
     */
    public function unreadConversationCount(): int
    {
        return ($this->countUnreadSupportConversations)();
    }

    /**
     * Archive (close) the conversation with a participant. Closing also marks
     * it read so closed tickets don't keep the sidebar badge lit.
     */
    public function archive(User $owner, User $participant): void
    {
        ($this->archiveConversation)($owner, $participant);
        $this->markAsRead($participant);
    }

    /**
     * Reopen (un-archive) the conversation with a participant.
     */
    public function reopen(User $participant): void
    {
        ($this->reopenConversation)($participant);
    }
}
