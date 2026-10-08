<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Models\Instructor;

class ApplyStripeAccountStatusAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Copy a Stripe Connect account's onboarding flags onto the instructor.
     *
     * Logs "Stripe connected" only on the first transition to complete, so
     * whichever arrives first (the account.updated webhook or the return-page
     * sync) logs it and the other is a no-op.
     *
     * @param  object  $account  Stripe Account object
     */
    public function __invoke(Instructor $instructor, object $account): Instructor
    {
        $wasComplete = (bool) $instructor->onboarding_complete;

        $instructor->onboarding_complete = $account->details_submitted ?? false;
        $instructor->charges_enabled = $account->charges_enabled ?? false;
        $instructor->payouts_enabled = $account->payouts_enabled ?? false;
        $instructor->save();

        if (! $wasComplete && $instructor->onboarding_complete) {
            ($this->logActivity)(
                $instructor,
                'Stripe account connected — onboarding completed',
                'profile',
                ['stripe_account_id' => $instructor->stripe_account_id],
                'Stripe connected'
            );
        }

        return $instructor;
    }
}
