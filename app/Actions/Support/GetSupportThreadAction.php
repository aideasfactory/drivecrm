<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class GetSupportThreadAction
{
    /**
     * Get the most recent messages between a participant and any owner (admin),
     * oldest first, so every admin sees the full support thread.
     *
     * @return Collection<int, Message>
     */
    public function __invoke(User $participant, int $limit = 100): Collection
    {
        $ownerIds = User::query()->select('id')->where('role', UserRole::OWNER);

        return Message::query()
            ->where(function ($query) use ($participant, $ownerIds) {
                $query->where(fn ($q) => $q->where('from', $participant->id)->whereIn('to', $ownerIds))
                    ->orWhere(fn ($q) => $q->where('to', $participant->id)->whereIn('from', $ownerIds));
            })
            ->with('sender:id,name,role')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->sortBy('created_at')
            ->values();
    }
}
