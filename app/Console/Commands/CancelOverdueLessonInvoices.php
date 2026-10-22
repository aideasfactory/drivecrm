<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\CancelOverdueLessonInvoiceAction;
use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use App\Support\BookingPayments;
use Illuminate\Console\Command;

/**
 * Failsafe for lessons that didn't go ahead but were never cancelled: voids
 * weekly lesson invoices still unpaid a week after the lesson
 * (`booking_payments.overdue_invoice_cancel_days`), so the pupil isn't chased
 * for them. The lessons themselves are not changed.
 */
class CancelOverdueLessonInvoices extends Command
{
    protected $signature = 'lessons:cancel-overdue-invoices';

    protected $description = 'Void weekly lesson invoices still unpaid a week after the lesson';

    public function handle(CancelOverdueLessonInvoiceAction $cancelInvoice): int
    {
        $cutoffDate = BookingPayments::overdueInvoiceCutoffDate();

        $lessonPayments = LessonPayment::query()
            ->where('status', PaymentStatus::DUE)
            ->whereNotNull('stripe_invoice_id')
            ->whereNull('invoice_cancelled_at')
            // Cancelled lessons have their invoices voided when they are cancelled.
            ->whereHas('lesson', function ($query) use ($cutoffDate) {
                $query->whereNotNull('date')
                    ->whereDate('date', '<', $cutoffDate)
                    ->where('status', '!=', LessonStatus::CANCELLED);
            })
            ->with('lesson.order.student')
            ->get();

        if ($lessonPayments->isEmpty()) {
            $this->info('No overdue invoices to cancel.');

            return 0;
        }

        $this->info("Found {$lessonPayments->count()} overdue invoice(s) for lessons before {$cutoffDate}.");

        $counts = ['voided' => 0, 'paid' => 0, 'failed' => 0];

        foreach ($lessonPayments as $lessonPayment) {
            $status = ($cancelInvoice)($lessonPayment);
            $counts[$status]++;

            $this->line("Lesson #{$lessonPayment->lesson_id} ({$lessonPayment->lesson->date->format('d M Y')}): {$status}");
        }

        $this->newLine();
        $this->info("Summary: {$counts['voided']} cancelled, {$counts['paid']} already paid, {$counts['failed']} failed");

        return $counts['failed'] > 0 ? 1 : 0;
    }
}
