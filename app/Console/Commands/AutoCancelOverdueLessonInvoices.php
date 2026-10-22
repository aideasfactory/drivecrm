<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\AutoCancelOverdueLessonPaymentAction;
use App\Actions\Payment\GetOverdueLessonPaymentsToAutoCancelAction;
use App\Support\BookingPayments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backstop for lessons nobody cancelled. A weekly lesson payment still unpaid
 * more than `auto_cancel_overdue_invoice_days` after it fell due has its
 * Stripe invoice voided and is marked auto-cancelled, so the pupil gets no
 * more payment emails or reminders for it. Instructors should still cancel
 * lessons properly; this only catches the ones they don't.
 */
class AutoCancelOverdueLessonInvoices extends Command
{
    protected $signature = 'lessons:auto-cancel-overdue-invoices';

    protected $description = 'Cancel weekly lesson invoices left unpaid too long after they fell due, and void them in Stripe';

    public function handle(
        GetOverdueLessonPaymentsToAutoCancelAction $getOverduePayments,
        AutoCancelOverdueLessonPaymentAction $autoCancelPayment,
    ): int {
        $days = BookingPayments::autoCancelOverdueInvoiceDays();
        $lessonPayments = ($getOverduePayments)();

        if ($lessonPayments->isEmpty()) {
            $this->info("No lesson invoices more than {$days} days overdue.");

            return Command::SUCCESS;
        }

        $this->info("Found {$lessonPayments->count()} lesson payment(s) more than {$days} days overdue.");

        $counts = ['cancelled' => 0, 'skipped' => 0, 'paid' => 0, 'failed' => 0];

        foreach ($lessonPayments as $lessonPayment) {
            try {
                $outcome = ($autoCancelPayment)($lessonPayment);
            } catch (Throwable $e) {
                $outcome = 'failed';

                Log::error('Exception auto-cancelling overdue lesson payment', [
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lessonPayment->lesson_id,
                    'error' => $e->getMessage(),
                ]);
            }

            $counts[$outcome]++;

            $this->line("Lesson payment #{$lessonPayment->id} (lesson #{$lessonPayment->lesson_id}, due {$lessonPayment->due_date?->toDateString()}): {$outcome}");
        }

        Log::info('Auto-cancel overdue lesson invoices finished', $counts);

        $this->newLine();
        $this->info("Summary: {$counts['cancelled']} cancelled, {$counts['paid']} found paid, {$counts['skipped']} skipped, {$counts['failed']} failed");

        return $counts['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
