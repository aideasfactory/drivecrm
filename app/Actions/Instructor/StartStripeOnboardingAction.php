<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use App\Services\StripeService;
use RuntimeException;

class StartStripeOnboardingAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Create the instructor's Stripe Connect account if they don't have one
     * yet, then mint a fresh onboarding Account Link. Account links are
     * single-use and expire after a few minutes, so callers must request a
     * new one immediately before redirecting.
     *
     * @return array{url: string, stripe_account_id: string}
     *
     * @throws RuntimeException
     */
    public function __invoke(Instructor $instructor, string $returnUrl, string $refreshUrl): array
    {
        if (! $instructor->stripe_account_id) {
            $accountResult = $this->stripeService->createConnectAccount($instructor);

            if (! $accountResult['success']) {
                throw new RuntimeException('Failed to create Stripe account: '.$accountResult['error']);
            }

            $instructor->stripe_account_id = $accountResult['account_id'];
            $instructor->save();
        }

        $linkResult = $this->stripeService->createAccountLink($instructor, $returnUrl, $refreshUrl);

        if (! $linkResult['success']) {
            throw new RuntimeException('Failed to create onboarding link: '.$linkResult['error']);
        }

        return [
            'url' => $linkResult['url'],
            'stripe_account_id' => $instructor->stripe_account_id,
        ];
    }
}
