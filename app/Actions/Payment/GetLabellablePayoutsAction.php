<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Payout;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetLabellablePayoutsAction
{
    /**
     * Payouts that went to an instructor through a Stripe transfer (payouts
     * paid manually outside Stripe have no transfer, so nothing to label),
     * oldest first.
     *
     * @return Collection<int, Payout>
     */
    public function __invoke(?int $instructorId = null, ?CarbonImmutable $since = null): Collection
    {
        return Payout::query()
            ->whereNotNull('stripe_transfer_id')
            ->when($instructorId !== null, fn ($query) => $query->where('instructor_id', $instructorId))
            ->when($since !== null, fn ($query) => $query->where('created_at', '>=', $since))
            ->with(['lesson.order.student', 'instructor.user'])
            ->orderBy('id')
            ->get();
    }
}
