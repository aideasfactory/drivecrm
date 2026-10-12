<?php

declare(strict_types=1);

namespace App\Actions\Owner;

use App\Enums\OwnerAccess;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UpdateOwnerAccessAction
{
    public function __invoke(User $owner, OwnerAccess $access, User $changedBy): User
    {
        $previousAccess = $owner->owner_access;

        $owner->update(['owner_access' => $access]);

        Log::info('Owner access updated', [
            'owner_user_id' => $owner->id,
            'previous_access' => $previousAccess?->value,
            'new_access' => $access->value,
            'changed_by_user_id' => $changedBy->id,
        ]);

        return $owner;
    }
}
