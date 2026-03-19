<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Shared\Message\GetConversationAction;
use App\Actions\Shared\Message\GetConversationsAction;
use App\Actions\Shared\Message\SendMessageAction;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MessageService extends BaseService
{
    public function __construct(
        protected GetConversationsAction $getConversations,
        protected GetConversationAction $getConversation,
        protected SendMessageAction $sendMessage
    ) {}

    /**
     * Get all conversations for a user.
     *
     * @return Collection<int, array{user: User, latest_message: Message}>
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
     * Send a message from one user to another.
     */
    public function sendMessage(
        User $sender,
        User $recipient,
        string $messageText,
        Student $student,
        Instructor $instructor
    ): Message {
        return ($this->sendMessage)($sender, $recipient, $messageText, $student, $instructor);
    }
}
