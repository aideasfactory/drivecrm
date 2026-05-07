<?php

declare(strict_types=1);

namespace App\Actions\Student\Transfer;

use App\Models\Instructor;
use Illuminate\Database\Eloquent\Collection;

class GetOnboardedInstructorsAction
{
    /**
     * Return instructors capable of receiving Stripe transfers, for the destination dropdown.
     * Filters to those who have completed Stripe Connect onboarding and have payouts enabled —
     * otherwise the next lesson sign-off would fail with a Stripe Transfer rejection.
     *
     * @return Collection<int, Instructor>
     */
    public function __invoke(): Collection
    {
        return Instructor::query()
            ->where('payouts_enabled', true)
            ->whereNotNull('stripe_account_id')
            ->with('user:id,name')
            ->get()
            ->sortBy(fn (Instructor $instructor) => $instructor->name)
            ->values();
    }
}
