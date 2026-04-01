<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Notifications\LessonPaymentReminderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stripe\StripeClient;

class ResendLessonInvoiceAction
{
    protected StripeClient $stripe;

    public function __construct(
        protected LogActivityAction $logActivity
    ) {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Resend the payment reminder email for a lesson that already has a Stripe invoice.
     *
     * @return array{success: bool, error?: string}
     */
    public function __invoke(LessonPayment $lessonPayment): array
    {
        if (! $lessonPayment->stripe_invoice_id) {
            return ['success' => false, 'error' => 'No invoice exists for this lesson payment'];
        }

        $lesson = $lessonPayment->lesson;
        $order = $lesson->order;
        $student = $order->student;

        try {
            $invoice = $this->stripe->invoices->retrieve($lessonPayment->stripe_invoice_id);
            $hostedInvoiceUrl = $invoice->hosted_invoice_url;

            if (! $hostedInvoiceUrl) {
                return ['success' => false, 'error' => 'Invoice does not have a payment URL'];
            }

            $this->sendReminderNotification($lessonPayment, $student, $hostedInvoiceUrl);

            Log::info('Lesson invoice resent successfully', [
                'lesson_payment_id' => $lessonPayment->id,
                'lesson_id' => $lesson->id,
                'invoice_id' => $lessonPayment->stripe_invoice_id,
                'student_id' => $student->id,
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Failed to resend lesson invoice', [
                'lesson_payment_id' => $lessonPayment->id,
                'stripe_invoice_id' => $lessonPayment->stripe_invoice_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Failed to retrieve invoice from Stripe'];
        }
    }

    protected function sendReminderNotification(LessonPayment $lessonPayment, Student $student, string $hostedInvoiceUrl): void
    {
        $isBookedByContact = ! $student->owns_account;

        if ($isBookedByContact) {
            $recipientEmail = $student->contact_email;
        } else {
            $recipientEmail = $student->email;
        }

        if (! $recipientEmail) {
            Log::warning('Cannot resend payment reminder: recipient email is missing', [
                'lesson_payment_id' => $lessonPayment->id,
                'student_id' => $student->id,
            ]);

            return;
        }

        Notification::route('mail', $recipientEmail)
            ->notify(new LessonPaymentReminderNotification($lessonPayment, $student, $hostedInvoiceUrl, $isBookedByContact));

        $lessonDate = $lessonPayment->lesson?->date?->format('d M Y') ?? 'N/A';

        ($this->logActivity)(
            $student,
            "Payment reminder email resent to {$recipientEmail} for lesson on {$lessonDate}",
            'notification',
            [
                'type' => 'lesson_payment_reminder_resend',
                'lesson_payment_id' => $lessonPayment->id,
                'recipient_email' => $recipientEmail,
                'is_booked_by_contact' => $isBookedByContact,
                'invoice_url' => $hostedInvoiceUrl,
            ]
        );
    }
}
