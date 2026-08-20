<?php

declare(strict_types=1);

namespace App\Actions\Bird;

use App\Models\BirdConversation;
use App\Models\BirdMessage;
use Illuminate\Database\Eloquent\Collection;

class GetBirdConversationsAction
{
    /**
     * All mirrored conversations ordered by the customer's most recent
     * message (not overall activity, which automated bot messages would keep
     * bumping). Conversations with no customer message yet sort last, then
     * by overall activity as a tiebreaker.
     *
     * @return Collection<int, BirdConversation>
     */
    public function __invoke(): Collection
    {
        $latestContactMessage = fn (string $column) => BirdMessage::query()
            ->select($column)
            ->whereColumn('bird_conversation_id', 'bird_conversations.id')
            ->where('sender_type', 'contact')
            ->orderByDesc('sent_at')
            ->limit(1);

        return BirdConversation::query()
            ->withCount('messages')
            ->addSelect([
                'last_contact_message_at' => $latestContactMessage('sent_at'),
                'last_contact_message' => $latestContactMessage('text'),
            ])
            ->orderByDesc('last_contact_message_at')
            ->orderByDesc('last_activity_at')
            ->get();
    }
}
