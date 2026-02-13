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
     * Frontend should reverse for chronological display.
     *
     * @param  User  $userA  First participant
     * @param  User  $userB  Second participant
     * @param  int  $perPage  Messages per page
     */
    public function __invoke(User $userA, User $userB, int $perPage = 30): LengthAwarePaginator
    {
        return Message::where(function ($query) use ($userA, $userB) {
            $query->where('from', $userA->id)->where('to', $userB->id);
        })->orWhere(function ($query) use ($userA, $userB) {
            $query->where('from', $userB->id)->where('to', $userA->id);
        })
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
