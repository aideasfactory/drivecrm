<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Models\Instructor;
use App\Models\User;

class MarkAppOnboardingCompleteAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Mark the mobile app onboarding slider finished for an instructor.
     *
     * Staff use this when an instructor is stuck on the in-app onboarding
     * step. It stamps the same columns the app already reads, so
     * `app_onboarding_complete` becomes true. Calling it again is a no-op
     * and does not move the original completion time.
     */
    public function __invoke(Instructor $instructor, User $performedBy): Instructor
    {
        if ($instructor->hasCompletedAppOnboarding()) {
            return $instructor;
        }

        $instructor->loadMissing('user');

        $instructor->app_onboarding_step = Instructor::APP_ONBOARDING_TOTAL_STEPS;
        $instructor->app_onboarding_completed_at = now();
        $instructor->save();

        $instructorName = $instructor->user?->name ?? 'this instructor';

        ($this->logActivity)(
            $instructor,
            "In-app onboarding marked complete for {$instructorName} by {$performedBy->name}.",
            'profile',
            [
                'performed_by_user_id' => $performedBy->id,
                'app_onboarding_step' => $instructor->app_onboarding_step,
            ],
            'In-app onboarding marked complete by staff',
        );

        return $instructor;
    }
}
