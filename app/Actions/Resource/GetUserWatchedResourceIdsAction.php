<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetUserWatchedResourceIdsAction
{
    /**
     * Get the IDs of resources the user has marked as watched.
     *
     * @return Collection<int, int>
     */
    public function __invoke(User $user): Collection
    {
        return DB::table('resource_watches')
            ->where('user_id', $user->id)
            ->pluck('resource_id');
    }
}
