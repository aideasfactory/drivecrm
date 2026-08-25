<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GetOnboardedInstructorsAction
{
    /**
     * Return instructors capable of receiving Stripe transfers, for the destination picker.
     * Filters to those who have completed Stripe Connect onboarding and have payouts enabled —
     * otherwise the next lesson sign-off would fail with a Stripe Transfer rejection.
     *
     * Optionally filters by a search term matched against the instructor's name and email
     * (held on the linked user) and phone number, and caps the result set for
     * typeahead-style lookups. Ordered by the linked user's name.
     *
     * @return Collection<int, Instructor>
     */
    public function __invoke(?string $search = null, ?int $limit = null): Collection
    {
        return Instructor::query()
            ->where('payouts_enabled', true)
            ->whereNotNull('stripe_account_id')
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
            ->orderBy(
                User::select('name')->whereColumn('users.id', 'instructors.user_id')
            )
            ->when($limit !== null, fn (Builder $query) => $query->limit($limit))
            ->get();
    }
}
