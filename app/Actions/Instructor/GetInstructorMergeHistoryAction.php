<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorMergeHistoryAction
{
    /**
     * Retired instructor profiles that were merged into another account, newest first.
     *
     * @return Collection<int, Instructor>
     */
    public function __invoke(int $limit = 20): Collection
    {
        return Instructor::query()
            ->whereNotNull('merged_at')
            ->with(['user:id,name,email', 'mergedInto.user:id,name,email', 'mergedBy:id,name'])
            ->latest('merged_at')
            ->limit($limit)
            ->get();
    }
}
