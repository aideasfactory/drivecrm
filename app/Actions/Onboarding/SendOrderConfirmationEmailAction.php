<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Order;
use App\Models\Student;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendOrderConfirmationEmailAction
{
    /**
     * Send order confirmation email to the appropriate recipient.
     *
     * Sends to:
     * - contact_email if owns_account = false (booking for someone else)
     * - email if owns_account = true (booking for self)
     */
    public function execute(Order $order, Student $student): void
    {
        try {
            $isBookedByContact = ! $student->owns_account;

            // Determine recipient email
            if ($isBookedByContact) {
                // Send to contact person
                $recipientEmail = $student->contact_email;
                $recipientName = trim(($student->contact_first_name ?? '').' '.($student->contact_surname ?? ''));
            } else {
                // Send to learner (student)
                $recipientEmail = $student->email;
                $recipientName = trim(($student->first_name ?? '').' '.($student->surname ?? ''));
            }

            if (! $recipientEmail) {
                Log::warning('Cannot send order confirmation: recipient email is missing', [
                    'order_id' => $order->id,
                    'student_id' => $student->id,
                    'owns_account' => $student->owns_account,
                ]);

                return;
            }

            // Create a temporary notifiable object for the email
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

            // Send notification
            Notification::send(
                $recipient,
                new OrderConfirmationNotification($order, $student, $isBookedByContact)
            );

            Log::info('Order confirmation email queued', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'recipient_email' => $recipientEmail,
                'is_booked_by_contact' => $isBookedByContact,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - email failure shouldn't break the checkout flow
        }
    }
}
