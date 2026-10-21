<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\Order;
use App\Notifications\InstructorPaidInFullPaymentReceivedNotification;
use App\Notifications\PaidInFullPaymentReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendPaidInFullPaymentEmailsAction
{
    /**
     * Send the "paid in full" payment confirmation to the student (or their
     * contact), with the full amount paid, its breakdown and every booked
     * lesson, and the matching "learner paid in full" email to the instructor
     * with the lesson fees they receive. Failures are logged and never thrown,
     * so they cannot affect confirming the payment.
     */
    public function __invoke(Order $order): void
    {
        $order->loadMissing(['student', 'instructor.user']);

        $student = $order->student;

        if (! $student) {
            Log::warning('Paid in full emails: no student found — skipping', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $isBookedByContact = ! $student->owns_account;

        try {
            $recipientEmail = $isBookedByContact ? $student->contact_email : $student->email;

            if ($recipientEmail) {
                Notification::route('mail', $recipientEmail)
                    ->notify(new PaidInFullPaymentReceivedNotification($order, $student, $isBookedByContact));

                Log::info('Paid in full payment email queued for student', [
                    'recipient_email' => $recipientEmail,
                    'order_id' => $order->id,
                ]);
            } else {
                Log::warning('Paid in full emails: no student email — skipping student notification', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send student paid in full payment email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $instructorEmail = $order->instructor?->user?->email;

            if ($instructorEmail) {
                Notification::route('mail', $instructorEmail)
                    ->notify(new InstructorPaidInFullPaymentReceivedNotification($order, $student));

                Log::info('Paid in full payment email queued for instructor', [
                    'instructor_email' => $instructorEmail,
                    'order_id' => $order->id,
                ]);
            } else {
                Log::warning('Paid in full emails: no instructor email — skipping instructor notification', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send instructor paid in full payment email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
