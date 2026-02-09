<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendLessonInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lessons:send-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Stripe invoices for lessons scheduled in the next 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(StripeService $stripeService)
    {
        $this->info('Checking for lessons that need invoices...');

        // Find lesson payments that are:
        // 1. Status = DUE (not paid yet)
        // 2. Due date is within the next 24 hours (or overdue)
        // 3. No invoice has been sent yet (stripe_invoice_id is null)
        // 4. Lesson has a scheduled_at date (weekly payment mode only)
        $lessonPayments = LessonPayment::where('status', PaymentStatus::DUE)
            ->whereNull('stripe_invoice_id')
            ->where('due_date', '<=', now()->addHours(24))
            ->whereHas('lesson', function ($query) {
                $query->whereNotNull('scheduled_at');
            })
            ->with(['lesson.order.student', 'lesson.order.package'])
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
            $student = $lesson->order->student;

            $this->info("Sending invoice for lesson #{$lesson->id} (scheduled: {$lesson->scheduled_at})");

            try {
                $result = $stripeService->createInvoice($lesson, $student);

                if ($result['success']) {
                    // Update lesson payment with invoice ID
                    $lessonPayment->update([
                        'stripe_invoice_id' => $result['invoice_id'],
                    ]);

                    $this->info("✓ Invoice sent successfully: {$result['hosted_invoice_url']}");
                    $successCount++;

                    Log::info('Lesson invoice sent', [
                        'lesson_id' => $lesson->id,
                        'invoice_id' => $result['invoice_id'],
                        'student_id' => $student->id,
                    ]);
                } else {
                    $this->error("✗ Failed to send invoice: {$result['error']}");
                    $failCount++;

                    Log::error('Failed to send lesson invoice', [
                        'lesson_id' => $lesson->id,
                        'error' => $result['error'],
                    ]);
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
