<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Services\StripeService;
use App\Support\BookingPayments;
use Illuminate\Support\Facades\Log;

class AutoCancelOverdueLessonPaymentAction
{
    public function __construct(
        protected StripeService $stripeService,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Stop a weekly lesson payment left unpaid too long after it fell due, as
     * a backstop for lessons nobody cancelled. Its Stripe invoice is voided
     * first so it can no longer be paid; only then is the payment marked
     * auto-cancelled, after which it is never invoiced or chased again. The
     * lesson itself is left alone: instructors still cancel lessons properly.
     *
     * Safe to run repeatedly. A payment already paid, refunded, confirmed as
     * paid another way or already auto-cancelled is skipped. An invoice found
     * paid is left for the `invoice.paid` webhook to record. When Stripe can't
     * void the invoice nothing changes, so the next run tries again.
     *
     * @return 'cancelled'|'skipped'|'paid'|'failed'
     */
    public function __invoke(LessonPayment $lessonPayment): string
    {
        $lessonPayment->refresh();

        if (! $lessonPayment->isDue() || $lessonPayment->isAutoCancelled() || $lessonPayment->hasAlternativePaymentConfirmed()) {
            return 'skipped';
        }

        if ($lessonPayment->stripe_invoice_id) {
            $result = $this->stripeService->voidInvoice($lessonPayment->stripe_invoice_id);

            if ($result['status'] === 'paid') {
                Log::warning('Auto-cancel skipped: overdue lesson invoice was found paid in Stripe', [
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lessonPayment->lesson_id,
                    'stripe_invoice_id' => $lessonPayment->stripe_invoice_id,
                ]);

                return 'paid';
            }

            if ($result['status'] !== 'voided') {
                Log::error('Auto-cancel failed: could not void overdue lesson invoice in Stripe', [
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lessonPayment->lesson_id,
                    'stripe_invoice_id' => $lessonPayment->stripe_invoice_id,
                ]);

                return 'failed';
            }
        }

        $lesson = $lessonPayment->lesson;
        $days = BookingPayments::autoCancelOverdueInvoiceDays();
        $dueDate = $lessonPayment->due_date?->format('j M Y') ?? 'unknown date';
        $lessonDate = $lesson?->date?->format('j M Y') ?? 'unknown date';
        $reason = "Unpaid more than {$days} days after it was due on {$dueDate} (lesson on {$lessonDate}), and the lesson was not signed off or cancelled.";

        // Conditional so a payment that lands meanwhile, or a parallel run, is never overwritten.
        $updated = LessonPayment::query()
            ->whereKey($lessonPayment->id)
            ->where('status', PaymentStatus::DUE)
            ->whereNull('auto_cancelled_at')
            ->whereNull('alternative_payment_confirmed_at')
            ->update([
                'auto_cancelled_at' => now(),
                'auto_cancellation_reason' => $reason,
            ]);

        if ($updated === 0) {
            return 'skipped';
        }

        Log::info('Overdue lesson payment auto-cancelled', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonPayment->lesson_id,
            'order_id' => $lesson?->order_id,
            'voided_invoice_id' => $lessonPayment->stripe_invoice_id,
            'due_date' => $lessonPayment->due_date?->toDateString(),
            'reason' => $reason,
        ]);

        $student = $lesson?->order?->student;

        if ($student) {
            ($this->logActivity)(
                $student,
                "Payment request for the lesson on {$lessonDate} cancelled automatically. {$reason}",
                'payment',
                [
                    'type' => 'lesson_payment_auto_cancelled',
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lessonPayment->lesson_id,
                    'order_id' => $lesson?->order_id,
                    'voided_invoice_id' => $lessonPayment->stripe_invoice_id,
                    'due_date' => $lessonPayment->due_date?->toDateString(),
                ],
                "Payment request for the lesson on {$lessonDate} cancelled automatically (unpaid {$days}+ days)"
            );
        }

        return 'cancelled';
    }
}
