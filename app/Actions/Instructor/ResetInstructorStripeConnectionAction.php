<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;

class ResetInstructorStripeConnectionAction
{
    /**
     * Forget the instructor's stored Stripe Connect account and the flags
     * that describe it, so the next connect (web or app) creates a new account.
     *
     * Does not call Stripe and does not delete anything on Stripe.
     */
    public function __invoke(Instructor $instructor): Instructor
    {
        $instructor->stripe_account_id = null;
        $instructor->onboarding_complete = false;
        $instructor->charges_enabled = false;
        $instructor->payouts_enabled = false;
        $instructor->save();

        return $instructor;
    }
}
