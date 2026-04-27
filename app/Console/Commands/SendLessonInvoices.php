<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payment\SendLessonInvoiceAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\LessonPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Manual catch-up command. Weekly invoices are now sent event-driven (at booking
 * time and on lesson sign-off) via OrderService::sendNextDueInvoice(). This command
 * is no longer scheduled — keep it as a fallback for sweeping any LessonPayments
 * that slipped through (e.g. an event-driven send failed).
 */
class SendLessonInvoices extends Command
{
    protected $signature = 'lessons:send-invoices';

    protected $description = 'Manual fallback: sweep weekly LessonPayments that have not been invoiced yet';

    public function handle(SendLessonInvoiceAction $sendInvoice): int
    {
        $this->info('Checking for lessons that need invoices...');

        $lessonPayments = LessonPayment::query()
            ->where('status', PaymentStatus::DUE)
            ->whereNull('stripe_invoice_id')
            ->where('due_date', '<=', now()->addHours(48))
            ->whereHas('lesson', function ($query) {
                $query->whereNotNull('date')
                    ->where('status', '!=', LessonStatus::CANCELLED);
            })
            ->whereHas('lesson.order', function ($query) {
                $query->where('status', OrderStatus::ACTIVE)
                    ->where('payment_mode', PaymentMode::WEEKLY);
            })
            ->whereHas('lesson.order.student.user', function ($query) {
                $query->whereNotNull('stripe_customer_id');
            })
            ->with(['lesson.order.student.user', 'lesson.order.package'])
            ->get();

        if ($lessonPayments->isEmpty()) {
            $this->info('No invoices to send.');

            return 0;
        }

        $this->info("Found {$lessonPayments->count()} lesson(s) that need invoices.");

        $successCount = 0;
        $failCount = 0;

        foreach ($lessonPayments as $lessonPayment) {
            $lesson = $lessonPayment->lesson;

            $this->info("Sending invoice for lesson #{$lesson->id} (date: {$lesson->date->format('d M Y')})");

            try {
                $result = ($sendInvoice)($lessonPayment);

                if ($result['success']) {
                    $this->info("✓ Invoice sent: {$result['hosted_invoice_url']}");
                    $successCount++;
                } else {
                    $this->error("✗ Failed: {$result['error']}");
                    $failCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Exception: {$e->getMessage()}");
                $failCount++;

                Log::error('Exception sending lesson invoice', [
                    'lesson_id' => $lesson->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary: {$successCount} succeeded, {$failCount} failed");

        return $failCount > 0 ? 1 : 0;
    }
}
