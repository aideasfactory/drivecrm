<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Payout;
use App\Services\StripeService;
use App\Support\PayoutDescriptions;
use Illuminate\Support\Facades\Log;

class LabelInstructorPayoutAction
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    /**
     * Label the payment a payout created in the instructor's Stripe account
     * with the learner, lesson date and booking number. Text only: the
     * payout, its amount and status are never changed. Returns false (and
     * logs) when there's nothing to label or Stripe refuses.
     */
    public function __invoke(Payout $payout, ?string $destinationPaymentId = null): bool
    {
        $payout->loadMissing(['lesson.order.student', 'instructor']);

        $lesson = $payout->lesson;
        $connectedAccountId = $payout->instructor?->stripe_account_id;

        if ($lesson === null || $connectedAccountId === null || $payout->stripe_transfer_id === null) {
            Log::info('Payout label skipped: no lesson, connected account or Stripe transfer', [
                'payout_id' => $payout->id,
            ]);

            return false;
        }

        $destinationPaymentId ??= $this->stripeService->getTransferDestinationPaymentId($payout->stripe_transfer_id);

        if ($destinationPaymentId === null) {
            return false;
        }

        $label = PayoutDescriptions::forLesson($lesson);

        $labelled = $this->stripeService->labelDestinationPayment(
            $destinationPaymentId,
            $connectedAccountId,
            $label['description'],
            [...$label['metadata'], 'payout_id' => (string) $payout->id],
        );

        if ($labelled) {
            Log::info('Instructor payment labelled in Stripe', [
                'payout_id' => $payout->id,
                'destination_payment' => $destinationPaymentId,
                'description' => $label['description'],
            ]);
        }

        return $labelled;
    }
}
