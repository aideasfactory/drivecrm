<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Notifications\LessonPaymentReminderNotification;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLessonInvoiceAction
{
    public function __construct(
        protected StripeService $stripeService,
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Create a Stripe invoice for a lesson payment and send a reminder notification.
     *
     * @return array{success: bool, invoice_id?: string, hosted_invoice_url?: string, error?: string}
     */
    public function __invoke(LessonPayment $lessonPayment): array
    {
        $lesson = $lessonPayment->lesson;
        $order = $lesson->order;
        $student = $order->student;
        $user = $student->user;

        // Guard: student must have a Stripe customer ID
        if (! $user->stripe_customer_id) {
            Log::warning('Cannot send lesson invoice: student has no Stripe customer ID', [
                'lesson_payment_id' => $lessonPayment->id,
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
            ]);

            return ['success' => false, 'error' => 'Student has no Stripe customer ID'];
        }

        // Create Stripe invoice
        $result = $this->stripeService->createInvoice($lesson, $user);

        if (! $result['success']) {
            Log::error('Failed to create Stripe invoice for lesson', [
                'lesson_payment_id' => $lessonPayment->id,
                'lesson_id' => $lesson->id,
                'error' => $result['error'],
            ]);

            return $result;
        }

        // Update lesson payment with invoice ID
        $lessonPayment->update([
            'stripe_invoice_id' => $result['invoice_id'],
        ]);

        // Send payment reminder notification
        $this->sendReminderNotification($lessonPayment, $student, $result['hosted_invoice_url']);

        Log::info('Lesson invoice sent successfully', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lesson->id,
            'invoice_id' => $result['invoice_id'],
            'student_id' => $student->id,
        ]);

        return $result;
    }

    /**
     * Send the payment reminder notification email.
     */
    protected function sendReminderNotification(LessonPayment $lessonPayment, Student $student, string $hostedInvoiceUrl): void
    {
        try {
            $isBookedByContact = ! $student->owns_account;

            if ($isBookedByContact) {
                $recipientEmail = $student->contact_email;
                $recipientName = trim(($student->contact_first_name ?? '').' '.($student->contact_surname ?? ''));
            } else {
                $recipientEmail = $student->email;
                $recipientName = trim(($student->first_name ?? '').' '.($student->surname ?? ''));
            }

            if (! $recipientEmail) {
                Log::warning('Cannot send payment reminder: recipient email is missing', [
                    'lesson_payment_id' => $lessonPayment->id,
                    'student_id' => $student->id,
                ]);

                return;
            }

            $recipient = new class($recipientEmail, $recipientName)
            {
                public function __construct(
                    public string $email,
                    public string $name
                ) {}

                public function routeNotificationForMail(): string
                {
                    return $this->email;
                }
            };

            Notification::send(
                $recipient,
                new LessonPaymentReminderNotification($lessonPayment, $student, $hostedInvoiceUrl, $isBookedByContact)
            );

            $lessonDate = $lessonPayment->lesson?->date?->format('d M Y') ?? 'N/A';

            ($this->logActivity)(
                $student,
                "Payment reminder email sent to {$recipientEmail} for lesson on {$lessonDate}",
                'notification',
                [
                    'type' => 'lesson_payment_reminder',
                    'lesson_payment_id' => $lessonPayment->id,
                    'recipient_email' => $recipientEmail,
                    'is_booked_by_contact' => $isBookedByContact,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder notification', [
                'lesson_payment_id' => $lessonPayment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
