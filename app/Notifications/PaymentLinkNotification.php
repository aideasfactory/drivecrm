<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public Student $student,
        public string $checkoutUrl,
        public bool $isBookedByContact
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
        $instructor = $order->instructor;
        $firstLesson = $order->lessons()->orderBy('date')->first();
        $totalFormatted = '£'.number_format($order->total_price_pence / 100, 2);

        $message = (new MailMessage)
            ->subject('Complete Your Payment for Driving Lessons')
            ->greeting($this->getGreeting())
            ->line($this->getIntroLine())
            ->line('**Booking Details:**')
            ->line("Package: {$order->package_name}")
            ->line("Number of lessons: {$order->package_lessons_count}")
            ->line("Instructor: {$instructor->user->name}")
            ->line("Total: {$totalFormatted}");

        if ($firstLesson) {
            $message->line('First lesson: '.\Carbon\Carbon::parse($firstLesson->date)->format('l, F j, Y'));
        }

        $message->line('')
            ->line('Please complete your payment using the link below to confirm your lessons.')
            ->action('Pay Now', $this->checkoutUrl)
            ->line('This payment link will expire after 24 hours.');

        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;
            $message->line('')
                ->line("This booking was made for: **{$learnerName}**");
        }

        $message->salutation('Safe driving,
The Driving School Team');

        return $message;
    }

    protected function getGreeting(): string
    {
        if ($this->isBookedByContact) {
            $contactName = $this->student->contact_first_name ?? 'there';

            return "Hello {$contactName}!";
        }

        $learnerName = $this->student->first_name ?? 'there';

        return "Hello {$learnerName}!";
    }

    protected function getIntroLine(): string
    {
        $instructorName = $this->order->instructor->user->name;

        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;

            return "Your instructor **{$instructorName}** has booked driving lessons for **{$learnerName}**. Please complete the payment to confirm the booking.";
        }

        return "Your instructor **{$instructorName}** has booked driving lessons for you. Please complete the payment to confirm the booking.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'package_name' => $this->order->package_name,
            'checkout_url' => $this->checkoutUrl,
        ];
    }
}
