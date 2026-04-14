<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkResourceWatchedAction
{
    /**
     * Mark a resource as watched by a user.
     *
     * Idempotent — calling multiple times for the same user/resource pair
     * will not create duplicate records.
     */
    public function __invoke(User $user, Resource $resource): void
    {
        DB::table('resource_watches')->insertOrIgnore([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'created_at' => now(),
        ]);
    }
}
