<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Validation\ValidationException;

class CompleteAppOnboardingStepAction
{
    /**
     * Mark an app onboarding step as complete for the instructor.
     *
     * Progression rules:
     * - Re-completing an already-completed step is an idempotent no-op.
     * - Steps cannot be skipped (step must be at most current + 1).
     * - Completing the final step stamps app_onboarding_completed_at
     *   server-side — the client never sends a completion flag.
     */
    public function __invoke(Instructor $instructor, int $step): Instructor
    {
        if ($step <= $instructor->app_onboarding_step) {
            return $instructor;
        }

        if ($step > $instructor->app_onboarding_step + 1) {
            throw ValidationException::withMessages([
                'step' => "Cannot complete step {$step} before completing step ".($instructor->app_onboarding_step + 1).'.',
            ]);
        }

        $instructor->app_onboarding_step = $step;

        if ($step === Instructor::APP_ONBOARDING_TOTAL_STEPS && $instructor->app_onboarding_completed_at === null) {
            $instructor->app_onboarding_completed_at = now();
        }

        $instructor->save();

        return $instructor;
    }
}
