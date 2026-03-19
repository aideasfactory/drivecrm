<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class GetConversationsAction
{
    /**
     * Get all conversations for a user, grouped by the other participant.
     *
     * Returns a collection of the latest message per conversation partner,
     * ordered by most recent first.
     *
     * @return Collection<int, array{user: User, latest_message: Message}>
     */
    public function __invoke(User $user): Collection
    {
        $userId = $user->id;

        $messages = Message::query()
            ->where('from', $userId)
            ->orWhere('to', $userId)
            ->with('sender:id,name', 'recipient:id,name')
            ->orderByDesc('created_at')
            ->get();

        $grouped = $messages->groupBy(function (Message $message) use ($userId) {
            return $message->from === $userId ? $message->to : $message->from;
        });

        return $grouped->map(function (Collection $conversationMessages, int $otherUserId) use ($userId) {
            /** @var Message $latestMessage */
            $latestMessage = $conversationMessages->first();
            $otherUser = $latestMessage->from === $userId
                ? $latestMessage->recipient
                : $latestMessage->sender;

            return [
                'user' => $otherUser,
                'latest_message' => $latestMessage,
            ];
        })->sortByDesc(fn (array $conversation) => $conversation['latest_message']->created_at)
            ->values();
    }
}
