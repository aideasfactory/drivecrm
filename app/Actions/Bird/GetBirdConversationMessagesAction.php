<?php

declare(strict_types=1);

namespace App\Actions\Bird;

use App\Models\BirdMessage;
use Illuminate\Database\Eloquent\Collection;

class GetBirdConversationMessagesAction
{
    /**
     * One conversation's messages in true chronological order.
     *
     * @return Collection<int, BirdMessage>
     */
    public function __invoke(string $conversationId): Collection
    {
        return BirdMessage::query()
            ->where('bird_conversation_id', $conversationId)
            ->orderBy('sent_at')
            ->get();
    }
}
