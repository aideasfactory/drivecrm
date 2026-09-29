<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells Head Office that a pupil paid for a booking after its hold ran out and
 * the lessons were released. The booking is not re-confirmed (the time may have
 * gone to someone else), so the payment has to be refunded manually. Works from
 * the order snapshot because a released order's lessons have been deleted.
 */
class ReleasedOrderPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $checkoutSessionId,
        public ?string $paymentReference,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $student = $order->student;
        $studentName = $student ? trim($student->first_name.' '.$student->surname) : 'Unknown student';
        $instructorName = $order->instructor?->user?->name ?? 'Unknown instructor';

        $breakdown = $order->firstPaymentBreakdown();
        $refundPence = $breakdown['total_pence'] - $breakdown['booking_fee_pence'];

        $message = (new MailMessage)
            ->subject('Action Required: Refund for a Payment on a Released Booking')
            ->greeting('Refund required')
            ->line('A pupil paid for a booking after the time to pay had run out and the lessons had been released. The booking has **not** been confirmed.')
            ->line('')
            ->line("**Student:** {$studentName}")
            ->line("**Instructor:** {$instructorName}")
            ->line('**Order:** #'.$order->id)
            ->line('**Payment mode:** '.$order->payment_mode->value)
            ->line('**Stripe checkout session:** '.($this->checkoutSessionId ?? 'n/a'))
            ->line('**Stripe payment:** '.($this->paymentReference ?? 'n/a'))
            ->line('')
            ->line('**Amount paid:** '.$this->formatPence($breakdown['total_pence']))
            ->line('Lessons: '.$this->formatPence($breakdown['lesson_pence']))
            ->line('Digital fee: '.$this->formatPence($breakdown['digital_fee_pence']));

        if ($breakdown['test_pass_guarantee_pence'] > 0) {
            $message->line('Pass Your Test Guarantee: '.$this->formatPence($breakdown['test_pass_guarantee_pence']));
        }

        return $message
            ->line('Booking fee (retained, not refunded): '.$this->formatPence($breakdown['booking_fee_pence']))
            ->line('')
            ->line('**Amount to refund:** '.$this->formatPence($refundPence))
            ->line('')
            ->line('Please action the refund manually in Stripe. No automatic refund has been issued.')
            ->salutation('— '.config('app.name'));
    }

    protected function formatPence(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'checkout_session_id' => $this->checkoutSessionId,
            'payment_reference' => $this->paymentReference,
        ];
    }
}
