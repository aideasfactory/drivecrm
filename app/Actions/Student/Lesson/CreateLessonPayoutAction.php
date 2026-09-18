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
use Illuminate\Support\Facades\Log;

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
        // payouts_enabled correlates with Stripe's 'transfers' capability — without it,
        // platform transfers to the connected account will fail
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

        // Resolve the funding charge so the transfer is tied to it via source_transaction.
        // May be null for legacy data that cannot be resolved — see resolveSourceTransaction.
        $sourceTransaction = $this->resolveSourceTransaction($lesson);

        // Create Stripe Transfer to instructor's connected account
        $transferResult = $this->stripeService->createTransfer(
            $lesson,
            $instructor,
            $lesson->amount_pence,
            $sourceTransaction
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

    /**
     * Resolve the Stripe charge id that funded this lesson, to pass as the transfer's
     * source_transaction.
     *
     * Upfront orders are funded by the order's charge; weekly lessons by their own
     * invoice's charge. The charge id is normally persisted at payment time (webhooks).
     * For legacy rows that predate that, lazily resolve it from the stored payment-intent
     * / invoice id, persist it, then use it. If it still cannot be resolved, return null
     * so the caller falls back to a plain transfer (no source_transaction) and log a
     * warning — existing flows must not break.
     */
    protected function resolveSourceTransaction(Lesson $lesson): ?string
    {
        $order = $lesson->order;

        if ($order === null) {
            return null;
        }

        if ($order->isUpfront()) {
            if ($order->stripe_charge_id) {
                return $order->stripe_charge_id;
            }

            // Legacy backfill from the stored payment intent.
            if ($order->stripe_payment_intent_id) {
                $chargeId = $this->stripeService->getChargeIdForPaymentIntent($order->stripe_payment_intent_id);

                if ($chargeId) {
                    $order->stripe_charge_id = $chargeId;
                    $order->save();

                    return $chargeId;
                }
            }

            Log::warning('Payout transfer falling back to plain transfer — no source charge resolvable for upfront order', [
                'lesson_id' => $lesson->id,
                'order_id' => $order->id,
            ]);

            return null;
        }

        // Weekly: the lesson's own invoice charge.
        $lessonPayment = $lesson->lessonPayment;

        if ($lessonPayment?->stripe_charge_id) {
            return $lessonPayment->stripe_charge_id;
        }

        // Legacy backfill from the stored invoice id.
        if ($lessonPayment?->stripe_invoice_id) {
            $chargeId = $this->stripeService->getChargeIdForInvoice($lessonPayment->stripe_invoice_id);

            if ($chargeId) {
                $lessonPayment->stripe_charge_id = $chargeId;
                $lessonPayment->save();

                return $chargeId;
            }
        }

        Log::warning('Payout transfer falling back to plain transfer — no source charge resolvable for weekly lesson', [
            'lesson_id' => $lesson->id,
            'order_id' => $order->id,
            'lesson_payment_id' => $lessonPayment?->id,
        ]);

        return null;
    }
}
