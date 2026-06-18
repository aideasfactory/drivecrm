<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Actions\Shared\LogActivityAction;
use App\Enums\ReminderType;
use App\Models\Lesson;
use App\Models\LessonReminder;
use App\Notifications\PaymentDueSoonNotification;
use App\Services\PushNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Stripe\StripeClient;

class SendPaymentReminderAction
{
    protected StripeClient $stripe;

    public function __construct(
        protected LogActivityAction $logActivity,
        protected PushNotificationService $pushNotificationService,
    ) {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Send a 48-hour payment-due reminder: always email the booker/learner,
     * additionally push when the learner owns the account and has a token, log
     * the activity, and record the reminder so it is never sent twice.
     */
    public function __invoke(Lesson $lesson): void
    {
        $student = $lesson->order?->student;
        $payment = $lesson->lessonPayment;

        if (! $student || ! $payment) {
            return;
        }

        $booker = $student->getBookerDetails();
        $recipientEmail = $booker['email'] ?? null;

        if (! $recipientEmail) {
            Log::warning('Cannot send 48h payment reminder: recipient email is missing', [
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
            ]);

            return;
        }

        // Reuse the Stripe hosted invoice URL (same source as the weekly reminder).
        $hostedInvoiceUrl = $this->resolveHostedInvoiceUrl($lesson, $payment->stripe_invoice_id);

        if (! $hostedInvoiceUrl) {
            return;
        }

        $isBookedByContact = ! $student->owns_account;

        Notification::route('mail', $recipientEmail)
            ->notify(new PaymentDueSoonNotification($payment, $student, $hostedInvoiceUrl, $isBookedByContact));

        // Additive push — only when the learner owns the account and has a token.
        if ($student->owns_account && $student->user?->expo_push_token) {
            $this->pushNotificationService->queueIfHasToken(
                $student->user,
                'Payment reminder',
                'Your lesson payment is due — tap to pay',
                [
                    'type' => ReminderType::PAYMENT_DUE_48H->value,
                    'lesson_id' => $lesson->id,
                    'hosted_invoice_url' => $hostedInvoiceUrl,
                ],
            );
        }

        $lessonDate = $lesson->date?->format('d M Y') ?? 'N/A';

        ($this->logActivity)(
            $student,
            'Payment-due (48h) reminder sent',
            'notification',
            [
                'type' => ReminderType::PAYMENT_DUE_48H->value,
                'lesson_id' => $lesson->id,
                'recipient_email' => $recipientEmail,
                'is_booked_by_contact' => $isBookedByContact,
            ],
        );

        LessonReminder::updateOrCreate(
            ['lesson_id' => $lesson->id, 'type' => ReminderType::PAYMENT_DUE_48H->value],
            ['sent_at' => CarbonImmutable::now()],
        );
    }

    /**
     * Retrieve the hosted invoice URL from the existing Stripe invoice. Returns
     * null (and logs) when there is no invoice yet or the lookup fails, so the
     * reminder is retried on a later run rather than sent without a pay link.
     */
    protected function resolveHostedInvoiceUrl(Lesson $lesson, ?string $stripeInvoiceId): ?string
    {
        if (! $stripeInvoiceId) {
            Log::warning('Cannot send 48h payment reminder: lesson payment has no Stripe invoice', [
                'lesson_id' => $lesson->id,
            ]);

            return null;
        }

        try {
            $invoice = $this->stripe->invoices->retrieve($stripeInvoiceId);

            return $invoice->hosted_invoice_url ?: null;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve Stripe invoice for 48h payment reminder', [
                'lesson_id' => $lesson->id,
                'stripe_invoice_id' => $stripeInvoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
