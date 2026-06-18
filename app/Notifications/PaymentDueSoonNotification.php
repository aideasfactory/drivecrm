<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentDueSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LessonPayment $lessonPayment,
        public Student $student,
        public string $hostedInvoiceUrl,
        public bool $isBookedByContact,
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
        $lesson = $this->lessonPayment->lesson;
        $order = $lesson->order;
        $lessonDate = $lesson->date->format('l, F j, Y');
        $lessonTime = $lesson->start_time->format('g:i A');
        $amount = $this->lessonPayment->formatted_amount;

        return (new MailMessage)
            ->subject("Payment Due Soon: Your Driving Lesson on {$lessonDate}")
            ->greeting($this->getGreeting())
            ->line($this->getIntroLine($lessonDate, $lessonTime))
            ->line('**Lesson Details:**')
            ->line("Package: {$order->package_name}")
            ->line("Date: {$lessonDate}")
            ->line("Time: {$lessonTime}")
            ->line("Amount due: {$amount}")
            ->line('')
            ->line('Your lesson is in less than 48 hours. Please complete your payment using the link below to secure it.')
            ->action('Pay Now', $this->hostedInvoiceUrl)
            ->line('If you have already paid, please disregard this email.')
            ->salutation('Safe driving,
The Driving School Team');
    }

    protected function getGreeting(): string
    {
        if ($this->isBookedByContact) {
            $name = $this->student->contact_first_name ?? 'there';

            return "Hello {$name}!";
        }

        $name = $this->student->first_name ?? 'there';

        return "Hello {$name}!";
    }

    protected function getIntroLine(string $lessonDate, string $lessonTime): string
    {
        $learnerName = trim(($this->student->first_name ?? '').' '.($this->student->surname ?? ''));

        if ($this->isBookedByContact) {
            return "This is a reminder that payment is due for **{$learnerName}'s** upcoming driving lesson on **{$lessonDate}** at **{$lessonTime}**.";
        }

        return "This is a reminder that payment is due for your upcoming driving lesson on **{$lessonDate}** at **{$lessonTime}**.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lesson_payment_id' => $this->lessonPayment->id,
            'lesson_id' => $this->lessonPayment->lesson_id,
            'amount' => $this->lessonPayment->formatted_amount,
        ];
    }
}
