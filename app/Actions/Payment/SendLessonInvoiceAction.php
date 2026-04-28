<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Notifications\LessonPaymentReminderNotification;
use App\Services\PushNotificationService;
use App\Services\StripeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLessonInvoiceAction
{
    public function __construct(
        protected StripeService $stripeService,
        protected LogActivityAction $logActivity,
        protected PushNotificationService $pushNotificationService
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

        // Create Stripe invoice using LessonPayment amount (cast to int)
        $result = $this->stripeService->createInvoice($lesson, $user, (int) $lessonPayment->amount_pence, $lessonPayment->id);

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

            Notification::route('mail', $recipientEmail)
                ->notify(new LessonPaymentReminderNotification($lessonPayment, $student, $hostedInvoiceUrl, $isBookedByContact));

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
                    'invoice_url' => $hostedInvoiceUrl,
                ]
            );

            // Additive push notification — only fires when the student owns the
            // account AND has registered an Expo push token. Contact-booked
            // accounts have no app login, so $student->user has no token; the
            // helper no-ops in that case. Push failure never affects the email.
            $this->sendReminderPush($lessonPayment, $student, $hostedInvoiceUrl);
        } catch (\Exception $e) {
            Log::error('Failed to send payment reminder notification', [
                'lesson_payment_id' => $lessonPayment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue a push notification mirroring the payment reminder email. No-ops
     * when the student's user has no Expo push token registered.
     */
    protected function sendReminderPush(LessonPayment $lessonPayment, Student $student, string $hostedInvoiceUrl): void
    {
        $user = $student->user;

        if (! $user || ! $user->expo_push_token) {
            return;
        }

        $lesson = $lessonPayment->lesson;
        $lessonDateShort = $lesson?->date?->format('D j M') ?? 'soon';

        $title = 'Time to pay for your lesson';
        $body = "Check your email to pay for your upcoming lesson on {$lessonDateShort}.";
        $data = [
            'type' => 'lesson_payment',
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonPayment->lesson_id,
            'hosted_invoice_url' => $hostedInvoiceUrl,
        ];

        $pushNotification = $this->pushNotificationService->queueIfHasToken($user, $title, $body, $data);

        if ($pushNotification) {
            ($this->logActivity)(
                $student,
                "Payment reminder push notification queued for user #{$user->id}",
                'notification',
                [
                    'type' => 'lesson_payment_reminder_push',
                    'lesson_payment_id' => $lessonPayment->id,
                    'push_notification_id' => $pushNotification->id,
                ]
            );
        }
    }
}
