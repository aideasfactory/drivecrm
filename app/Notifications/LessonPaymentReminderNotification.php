<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LessonPayment $lessonPayment,
        public Student $student,
        public string $hostedInvoiceUrl,
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
        $lesson = $this->lessonPayment->lesson;
        $order = $lesson->order;
        $lessonDate = $lesson->date->format('l, F j, Y');
        $lessonTime = $lesson->start_time->format('g:i A');
        $amount = $this->lessonPayment->formatted_amount;

        $message = (new MailMessage)
            ->subject("Payment Required: Your Driving Lesson on {$lessonDate}")
            ->greeting($this->getGreeting())
            ->line($this->getIntroLine($lessonDate, $lessonTime))
            ->line('**Lesson Details:**')
            ->line("Package: {$order->package_name}")
            ->line("Date: {$lessonDate}")
            ->line("Time: {$lessonTime}")
            ->line("Amount due: {$amount}")
            ->line('')
            ->line('Please complete your payment using the link below to secure your lesson.')
            ->action('Pay Now', $this->hostedInvoiceUrl)
            ->line('If you have already paid, please disregard this email.')
            ->salutation('Safe driving,
The Driving School Team');

        return $message;
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
        $learnerName = $this->student->first_name.' '.$this->student->surname;

        if ($this->isBookedByContact) {
            return "This is a friendly reminder that payment is required for **{$learnerName}'s** upcoming driving lesson on **{$lessonDate}** at **{$lessonTime}**.";
        }

        return "This is a friendly reminder that payment is required for your upcoming driving lesson on **{$lessonDate}** at **{$lessonTime}**.";
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
