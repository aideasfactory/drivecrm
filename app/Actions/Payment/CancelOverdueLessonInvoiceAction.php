<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Models\LessonPayment;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;

/**
 * Failsafe for lessons that didn't go ahead but were never cancelled: void the
 * lesson's Stripe invoice once it is still unpaid a week after the lesson, so
 * the pupil can no longer pay it. The lesson itself is left as it is.
 */
class CancelOverdueLessonInvoiceAction
{
    public function __construct(
        protected StripeService $stripeService,
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Never throws. An invoice found already paid is left for the
     * `invoice.paid` webhook to record.
     *
     * @return 'voided'|'paid'|'failed'
     */
    public function __invoke(LessonPayment $lessonPayment): string
    {
        $result = $this->stripeService->voidInvoice($lessonPayment->stripe_invoice_id);

        if ($result['status'] !== 'voided') {
            Log::warning('Overdue lesson invoice not cancelled', [
                'lesson_payment_id' => $lessonPayment->id,
                'lesson_id' => $lessonPayment->lesson_id,
                'stripe_invoice_id' => $lessonPayment->stripe_invoice_id,
                'stripe_status' => $result['status'],
            ]);

            return $result['status'];
        }

        $lessonPayment->update(['invoice_cancelled_at' => now()]);

        Log::info('Overdue lesson invoice cancelled', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonPayment->lesson_id,
            'stripe_invoice_id' => $lessonPayment->stripe_invoice_id,
        ]);

        $this->logCancellation($lessonPayment);

        return 'voided';
    }

    protected function logCancellation(LessonPayment $lessonPayment): void
    {
        try {
            $student = $lessonPayment->lesson?->order?->student;

            if (! $student) {
                return;
            }

            $lessonDate = $lessonPayment->lesson->date?->format('d M Y') ?? 'N/A';

            ($this->logActivity)(
                $student,
                "Unpaid invoice for the lesson on {$lessonDate} cancelled automatically (still unpaid a week after the lesson)",
                'payment',
                [
                    'type' => 'overdue_lesson_invoice_cancelled',
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lessonPayment->lesson_id,
                    'stripe_invoice_id' => $lessonPayment->stripe_invoice_id,
                ],
                "Unpaid invoice for the lesson on {$lessonDate} cancelled"
            );
        } catch (\Throwable $e) {
            Log::error('Failed to log overdue invoice cancellation', [
                'lesson_payment_id' => $lessonPayment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
