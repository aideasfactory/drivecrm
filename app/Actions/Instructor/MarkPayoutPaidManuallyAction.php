<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Validation\ValidationException;

class MarkPayoutPaidManuallyAction
{
    /**
     * Admin override: record a failed payout as paid after staff paid the
     * instructor outside the platform (e.g. a manual transfer in Stripe).
     *
     * Moves no money and makes no Stripe call. The failure code and message
     * are kept so the record still shows why it was paid by hand, and
     * `stripe_transfer_id` stays null.
     *
     * @throws ValidationException when the payout is not in a failed state
     */
    public function __invoke(Payout $payout): Payout
    {
        if (! $payout->isFailed()) {
            throw ValidationException::withMessages([
                'payout' => 'Only payouts that need payment can be marked as paid manually.',
            ]);
        }

        $payout->status = PayoutStatus::PAID;
        $payout->paid_at = now();
        $payout->save();

        return $payout;
    }
}
