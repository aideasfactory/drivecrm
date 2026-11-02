<?php

declare(strict_types=1);

namespace App\Actions\Shared\Message;

use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetConversationAction
{
    /**
     * Get paginated conversation messages between two users.
     *
     * Returns messages ordered by newest first (for pagination).
     * Frontend should reverse for chronological display. A conversation
     * with an owner (admin) includes messages with every owner — see
     * Message::scopeSentBetween().
     *
     * @param  User  $userA  First participant
     * @param  User  $userB  Second participant
     * @param  int  $perPage  Messages per page
     */
    public function __invoke(User $userA, User $userB, int $perPage = 30): LengthAwarePaginator
    {
        return Message::where(fn ($query) => $query->sentBetween($userA, $userB))
            ->orWhere(fn ($query) => $query->sentBetween($userB, $userA))
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
