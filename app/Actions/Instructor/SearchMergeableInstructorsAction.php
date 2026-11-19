<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\InstructorStatus;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SearchMergeableInstructorsAction
{
    /**
     * Instructors that can be picked on either side of a merge (not archived, never merged away),
     * matched on name, email or phone, with their pupil count.
     *
     * @return Collection<int, Instructor>
     */
    public function __invoke(?string $search = null, int $limit = 25): Collection
    {
        return Instructor::query()
            ->where('status', '!=', InstructorStatus::Archived->value)
            ->whereNull('merged_at')
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('phone', 'like', $like)
                        ->orWhereHas('user', function (Builder $user) use ($like): void {
                            $user->where(function (Builder $match) use ($like): void {
                                $match->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like);
                            });
                        });
                });
            })
            ->with('user:id,name,email')
            ->withCount('students')
            ->orderBy(User::select('name')->whereColumn('users.id', 'instructors.user_id'))
            ->limit($limit)
            ->get();
    }
}
