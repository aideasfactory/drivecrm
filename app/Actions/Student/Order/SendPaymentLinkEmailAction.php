<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\Shared\LogActivityAction;
use App\Models\Order;
use App\Models\Student;
use App\Notifications\PaymentLinkNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendPaymentLinkEmailAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Send payment link email to the student or their contact person.
     */
    public function execute(Order $order, Student $student, string $checkoutUrl): void
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
                Log::warning('Cannot send payment link: recipient email is missing', [
                    'order_id' => $order->id,
                    'student_id' => $student->id,
                    'owns_account' => $student->owns_account,
                ]);

                return;
            }

            Notification::route('mail', $recipientEmail)
                ->notify(new PaymentLinkNotification($order, $student, $checkoutUrl, $isBookedByContact));

            Log::info('Payment link email queued', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'recipient_email' => $recipientEmail,
                'is_booked_by_contact' => $isBookedByContact,
            ]);

            ($this->logActivity)(
                $student,
                "Payment link email sent to {$recipientEmail}",
                'notification',
                [
                    'type' => 'payment_link',
                    'order_id' => $order->id,
                    'recipient_email' => $recipientEmail,
                    'is_booked_by_contact' => $isBookedByContact,
                ]
            );

            if ($order->instructor) {
                ($this->logActivity)(
                    $order->instructor,
                    "Payment link email sent to {$recipientName} ({$recipientEmail})",
                    'notification',
                    [
                        'type' => 'payment_link',
                        'order_id' => $order->id,
                        'student_id' => $student->id,
                        'recipient_email' => $recipientEmail,
                    ]
                );
            }

        } catch (\Exception $e) {
            Log::error('Failed to send payment link email', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
