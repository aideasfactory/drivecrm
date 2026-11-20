<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Shared\LogActivityAction;
use App\Enums\PaymentStatus;
use App\Models\LessonDeletionRequest;
use App\Models\LessonPayment;
use App\Models\Student;
use App\Notifications\LessonPaymentReminderNotification;
use App\Services\PushNotificationService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
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
     * The lesson payment row is locked (SELECT ... FOR UPDATE) and re-checked
     * before Stripe is called, and stays locked until the invoice id is saved.
     * Approving a lesson deletion locks the same row, so the two can't overlap:
     * a payment that has gone, is no longer due, is already invoiced or whose
     * lesson has a pending deletion request is skipped (`skipped: true`) and
     * Stripe is never called. The reminder email is sent after the commit.
     *
     * @return array{success: bool, skipped?: bool, invoice_id?: string, hosted_invoice_url?: string, error?: string}
     */
    public function __invoke(LessonPayment $lessonPayment): array
    {
        $outcome = DB::transaction(function () use ($lessonPayment): array {
            $lockedPayment = LessonPayment::query()->whereKey($lessonPayment->id)->lockForUpdate()->first();
            $skipReason = $this->skipReason($lockedPayment);

            if ($skipReason !== null) {
                Log::info('Lesson invoice skipped', [
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lessonPayment->lesson_id,
                    'reason' => $skipReason,
                ]);

                return ['result' => ['success' => false, 'skipped' => true, 'error' => $skipReason]];
            }

            $lessonPayment->setRawAttributes($lockedPayment->getAttributes(), true);

            return $this->createInvoice($lessonPayment);
        });

        $result = $outcome['result'];

        if (! $result['success']) {
            return $result;
        }

        $this->sendReminderNotification($lessonPayment, $outcome['student'], $result['hosted_invoice_url'], $outcome['breakdown']);

        Log::info('Lesson invoice sent successfully', [
            'lesson_payment_id' => $lessonPayment->id,
            'lesson_id' => $lessonPayment->lesson_id,
            'invoice_id' => $result['invoice_id'],
            'student_id' => $outcome['student']->id,
        ]);

        return $result;
    }

    protected function skipReason(?LessonPayment $lockedPayment): ?string
    {
        if (! $lockedPayment) {
            return 'This lesson payment no longer exists.';
        }

        if ($lockedPayment->status !== PaymentStatus::DUE) {
            return 'This lesson payment is no longer due.';
        }

        if ($lockedPayment->stripe_invoice_id !== null) {
            return 'This lesson payment has already been invoiced.';
        }

        if (LessonDeletionRequest::query()->where('pending_lesson_id', $lockedPayment->lesson_id)->exists()) {
            return 'This lesson has a pending deletion request.';
        }

        return null;
    }

    /**
     * Runs inside the lock taken in __invoke.
     *
     * @return array{result: array<string, mixed>, student?: Student, breakdown?: array<string, int>|null}
     */
    protected function createInvoice(LessonPayment $lessonPayment): array
    {
        $lesson = $lessonPayment->lesson;
        $order = $lesson->order;
        $student = $order->student;
        $user = $student->user;

        // A pupil who never opened a card checkout (e.g. the instructor confirmed
        // an alternative payment first) has no Stripe customer yet — create one
        // so later weeks can still be invoiced and paid by card.
        if (! $user->stripe_customer_id) {
            $customerResult = $this->stripeService->createOrGetCustomer($user);

            if (! $customerResult['success']) {
                Log::warning('Cannot send lesson invoice: Stripe customer could not be created', [
                    'lesson_payment_id' => $lessonPayment->id,
                    'lesson_id' => $lesson->id,
                    'student_id' => $student->id,
                    'error' => $customerResult['error'] ?? 'Unknown',
                ]);

                return ['result' => ['success' => false, 'error' => 'Student has no Stripe customer ID']];
            }

            $user->stripe_customer_id = $customerResult['customer_id'];
            $user->save();
        }

        $amountPence = (int) $lessonPayment->amount_pence;
        $breakdown = LessonPayment::weeklyBreakdown($order, $amountPence, (int) $lessonPayment->test_pass_guarantee_pence);

        // Create Stripe invoice using LessonPayment amount and the breakdown
        // so the hosted invoice itemises lesson cost, booking fee and digital
        // fee separately.
        $result = $this->stripeService->createInvoice($lesson, $user, $amountPence, $lessonPayment->id, $breakdown);

        if (! $result['success']) {
            Log::error('Failed to create Stripe invoice for lesson', [
                'lesson_payment_id' => $lessonPayment->id,
                'lesson_id' => $lesson->id,
                'error' => $result['error'],
            ]);

            return ['result' => $result];
        }

        $lessonPayment->update([
            'stripe_invoice_id' => $result['invoice_id'],
        ]);

        return ['result' => $result, 'student' => $student, 'breakdown' => $breakdown];
    }

    /**
     * Send the payment reminder notification email.
     *
     * @param  array{lesson: int, booking_fee: int, digital_fee: int, test_pass_guarantee?: int}|null  $breakdown
     */
    protected function sendReminderNotification(LessonPayment $lessonPayment, Student $student, string $hostedInvoiceUrl, ?array $breakdown = null): void
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
                ->notify(new LessonPaymentReminderNotification($lessonPayment, $student, $hostedInvoiceUrl, $isBookedByContact, $breakdown));

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
                ],
                "Payment reminder email sent for the lesson on {$lessonDate}"
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
                ],
                'Payment reminder push notification sent'
            );
        }
    }
}
