<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Services\StripeService;
use RuntimeException;

class SyncStripeAccountStatusAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Pull the live account state from Stripe and persist the onboarding
     * flags. No-op when the instructor has no Stripe account yet.
     *
     * @throws RuntimeException
     */
    public function __invoke(Instructor $instructor): Instructor
    {
        if (! $instructor->stripe_account_id) {
            return $instructor;
        }

        $accountResult = $this->stripeService->retrieveAccount($instructor->stripe_account_id);

        if (! $accountResult['success']) {
            throw new RuntimeException('Failed to retrieve Stripe account: '.$accountResult['error']);
        }

        $account = $accountResult['account'];

        $instructor->onboarding_complete = $account->details_submitted ?? false;
        $instructor->charges_enabled = $account->charges_enabled ?? false;
        $instructor->payouts_enabled = $account->payouts_enabled ?? false;
        $instructor->save();

        return $instructor;
    }
}
