<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;

class ForceCompleteAppOnboardingAction
{
    /**
     * Admin override: push the instructor past the mobile app onboarding slider.
     *
     * Jumps straight to the final step and stamps app_onboarding_completed_at,
     * bypassing the one-step-at-a-time rule in CompleteAppOnboardingStepAction.
     * Idempotent — an already-completed instructor keeps their original timestamp.
     * Does not touch the Stripe Connect `onboarding_complete` flag.
     */
    public function __invoke(Instructor $instructor): Instructor
    {
        if ($instructor->hasCompletedAppOnboarding()) {
            return $instructor;
        }

        $instructor->app_onboarding_step = Instructor::APP_ONBOARDING_TOTAL_STEPS;
        $instructor->app_onboarding_completed_at = now();
        $instructor->save();

        return $instructor;
    }
}
