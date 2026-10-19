<?php

declare(strict_types=1);

namespace App\Actions\Student\Order;

use App\Actions\Shared\LogActivityAction;
use App\Models\Order;
use App\Models\Student;
use App\Notifications\CashBookingConfirmedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendCashBookingConfirmationEmailAction
{
    public function __construct(
        protected LogActivityAction $logActivity
    ) {}

    /**
     * Email the cash booking confirmation to the learner, or to the contact who
     * booked for them. A mail failure is logged and never undoes the booking.
     */
    public function __invoke(Order $order, Student $student): void
    {
        try {
            $isBookedByContact = ! $student->owns_account;
            $recipientEmail = $isBookedByContact ? $student->contact_email : $student->email;

            if (! $recipientEmail) {
                Log::warning('Cannot send cash booking confirmation: recipient email is missing', [
                    'order_id' => $order->id,
                    'student_id' => $student->id,
                ]);

                return;
            }

            Notification::route('mail', $recipientEmail)
                ->notify(new CashBookingConfirmedNotification($order, $student, $isBookedByContact));

            ($this->logActivity)(
                $student,
                "Cash booking confirmation email sent to {$recipientEmail}",
                'notification',
                [
                    'type' => 'cash_booking_confirmation',
                    'order_id' => $order->id,
                    'recipient_email' => $recipientEmail,
                    'is_booked_by_contact' => $isBookedByContact,
                ],
                'Cash booking confirmation email sent'
            );
        } catch (\Exception $e) {
            Log::error('Failed to send cash booking confirmation email', [
                'order_id' => $order->id,
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
