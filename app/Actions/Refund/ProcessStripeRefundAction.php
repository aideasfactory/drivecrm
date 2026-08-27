<?php

declare(strict_types=1);

namespace App\Actions\Refund;

use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcessStripeRefundAction
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    /**
     * Issue a Stripe refund for a pending request and record the paper trail.
     */
    public function __invoke(Refund $refund, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $actor): Refund {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $refund->loadMissing(['lesson.order.student', 'lessonPayment', 'order', 'processedBy']);

            if (! $refund->isPending()) {
                throw new RuntimeException('This refund has already been completed.');
            }

            $chargeId = $this->resolveChargeId($refund);

            if (! $chargeId) {
                throw new RuntimeException('No Stripe charge is on file for this payment. Refund it in Stripe, then mark it complete.');
            }

            $result = $this->stripeService->createRefund(
                $chargeId,
                $refund->amount_pence,
                [
                    'refund_id' => (string) $refund->id,
                    'lesson_id' => (string) $refund->lesson_id,
                    'processed_by_user_id' => (string) $actor->id,
                ],
                'refund-'.$refund->id,
            );

            if (! ($result['success'] ?? false)) {
                throw new RuntimeException($result['error'] ?? 'Stripe refund failed.');
            }

            $refund->status = RefundStatus::COMPLETED;
            $refund->method = RefundMethod::STRIPE;
            $refund->stripe_refund_id = $result['refund_id'] ?? null;
            $refund->processed_by_user_id = $actor->id;
            $refund->completed_at = now();
            $refund->save();

            $this->markLessonPaymentRefunded($refund);
            $this->logPaperTrail($refund->fresh(['processedBy', 'student', 'lesson']), $actor);

            return $refund->fresh(['processedBy', 'requestedBy', 'student', 'instructor', 'lesson', 'order']) ?? $refund;
        });
    }

    protected function resolveChargeId(Refund $refund): ?string
    {
        $lessonPayment = $refund->lessonPayment ?? $refund->lesson?->lessonPayment;

        if ($lessonPayment?->stripe_charge_id) {
            return $lessonPayment->stripe_charge_id;
        }

        if ($lessonPayment?->stripe_invoice_id) {
            $chargeId = $this->stripeService->getChargeIdForInvoice($lessonPayment->stripe_invoice_id);

            if ($chargeId) {
                $lessonPayment->stripe_charge_id = $chargeId;
                $lessonPayment->save();

                return $chargeId;
            }
        }

        $order = $refund->order ?? $refund->lesson?->order;

        if ($order?->stripe_charge_id) {
            return $order->stripe_charge_id;
        }

        if ($order?->stripe_payment_intent_id) {
            $chargeId = $this->stripeService->getChargeIdForPaymentIntent($order->stripe_payment_intent_id);

            if ($chargeId) {
                $order->stripe_charge_id = $chargeId;
                $order->save();

                return $chargeId;
            }
        }

        return null;
    }

    protected function markLessonPaymentRefunded(Refund $refund): void
    {
        $lessonPayment = $refund->lessonPayment ?? $refund->lesson?->lessonPayment;

        if (! $lessonPayment) {
            return;
        }

        $lessonPayment->status = PaymentStatus::REFUNDED;
        $lessonPayment->save();
    }

    protected function logPaperTrail(Refund $refund, User $actor): void
    {
        $student = $refund->student ?? $refund->lesson?->order?->student;

        if (! $student) {
            return;
        }

        $trail = $refund->paperTrail() ?? ($actor->name.' made refund on '.now()->format('d/m/Y H:i'));

        $student->logActivity(
            $trail.' for lesson #'.$refund->lesson_id.' ('.$refund->formatted_amount.')',
            'payment',
            [
                'refund_id' => $refund->id,
                'lesson_id' => $refund->lesson_id,
                'amount_pence' => $refund->amount_pence,
                'method' => $refund->method?->value,
                'stripe_refund_id' => $refund->stripe_refund_id,
                'processed_by_user_id' => $actor->id,
            ],
            $trail,
        );
    }
}
