<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\User;

class CountUnreadSupportConversationsAction
{
    /**
     * Count participants (instructors/pupils) with at least one unread
     * message addressed to any owner (admin). Single COUNT(DISTINCT) query
     * on the (to, read_at) index — cheap enough to share on every request.
     */
    public function __invoke(): int
    {
        $ownerIds = User::query()->select('id')->where('role', UserRole::OWNER);

        return Message::query()
            ->whereIn('to', $ownerIds)
            ->whereNotIn('from', $ownerIds)
            ->unread()
            ->distinct()
            ->count('from');
    }
}
