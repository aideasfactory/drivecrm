<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Shared\Message\GetConversationAction;
use App\Actions\Shared\Message\GetConversationsAction;
use App\Actions\Shared\Message\GetUnreadCountsAction;
use App\Actions\Shared\Message\MarkConversationAsReadAction;
use App\Actions\Shared\Message\SendMessageAction;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MessageService extends BaseService
{
    public function __construct(
        protected GetConversationsAction $getConversations,
        protected GetConversationAction $getConversation,
        protected SendMessageAction $sendMessage,
        protected MarkConversationAsReadAction $markConversationAsRead,
        protected GetUnreadCountsAction $getUnreadCounts
    ) {}

    /**
     * Get all conversations for a user.
     *
     * @return Collection<int, array{user: User, student_id: int|null, latest_message: Message, unread_count: int}>
     */
    public function getConversations(User $user): Collection
    {
        return ($this->getConversations)($user);
    }

    /**
     * Get paginated messages between two users.
     */
    public function getConversationMessages(User $userA, User $userB, int $perPage = 30): LengthAwarePaginator
    {
        return ($this->getConversation)($userA, $userB, $perPage);
    }

    /**
     * Send a direct message from one user to another. Email, push, and
     * activity logging are fired by MessageObserver on the created row.
     */
    public function sendMessage(User $sender, User $recipient, string $messageText): Message
    {
        return ($this->sendMessage)($sender, $recipient, $messageText);
    }

    /**
     * Mark all unread messages sent by another user to the reader as read.
     *
     * Returns the number of messages newly marked read (idempotent).
     */
    public function markConversationAsRead(User $reader, User $otherUser): int
    {
        return ($this->markConversationAsRead)($reader, $otherUser);
    }

    /**
     * Mark a single message as read.
     *
     * Returns true if the message was newly marked, false if already read.
     */
    public function markMessageAsRead(Message $message): bool
    {
        return $message->markAsRead();
    }

    /**
     * Get unread message counts for a user (total + per-conversation).
     *
     * Not cached — unread counts are volatile and must be real-time.
     *
     * @return array{total: int, conversations: array<int, array{user_id: int, student_id: int|null, unread_count: int}>}
     */
    public function getUnreadCounts(User $user): array
    {
        return ($this->getUnreadCounts)($user);
    }
}
