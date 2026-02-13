<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\PayoutStatus;
use App\Exceptions\InstructorNotOnboardedException;
use App\Exceptions\PayoutAlreadyProcessedException;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Payout;
use App\Services\StripeService;
use Exception;

class CreateLessonPayoutAction
{
    public function __construct(
        protected StripeService $stripeService
    ) {}

    /**
     * Create a payout record and Stripe transfer for a completed lesson.
     *
     * Mirrors the v1 LessonController@complete payout logic:
     * 1. Validate instructor onboarding + payouts enabled
     * 2. Create Payout record (pending)
     * 3. Create Stripe Transfer to instructor's Connect account
     * 4. Update Payout with transfer ID and mark as paid
     *
     * @throws InstructorNotOnboardedException
     * @throws PayoutAlreadyProcessedException
     * @throws Exception
     */
    public function __invoke(Lesson $lesson, Instructor $instructor): Payout
    {
        // Guard: payout already exists
        if ($lesson->hasPayoutProcessed()) {
            throw new PayoutAlreadyProcessedException;
        }

        // Guard: instructor must be onboarded and payouts enabled
        if (! $instructor->onboarding_complete || ! $instructor->payouts_enabled) {
            throw new InstructorNotOnboardedException;
        }

        // Create payout record with pending status
        $payout = Payout::create([
            'lesson_id' => $lesson->id,
            'instructor_id' => $instructor->id,
            'amount_pence' => $lesson->amount_pence,
            'status' => PayoutStatus::PENDING,
        ]);

        // Create Stripe Transfer to instructor's connected account
        $transferResult = $this->stripeService->createTransfer(
            $lesson,
            $instructor,
            $lesson->amount_pence
        );

        if (! $transferResult['success']) {
            // Mark payout as failed
            $payout->status = PayoutStatus::FAILED;
            $payout->save();

            throw new Exception('Stripe transfer failed: '.$transferResult['error']);
        }

        // Update payout with Stripe transfer details
        $payout->stripe_transfer_id = $transferResult['transfer_id'];
        $payout->status = PayoutStatus::PAID;
        $payout->paid_at = now();
        $payout->save();

        return $payout;
    }
}
