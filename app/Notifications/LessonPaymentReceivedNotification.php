<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonPaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LessonPayment $lessonPayment,
        public Student $student,
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
        $instructorName = $order->instructor?->user?->name ?? 'your instructor';

        return (new MailMessage)
            ->subject("Payment Confirmed: Your Driving Lesson on {$lessonDate}")
            ->greeting($this->getGreeting())
            ->line($this->getIntroLine($lessonDate, $lessonTime, $amount))
            ->line('**Lesson Details:**')
            ->line("Package: {$order->package_name}")
            ->line("Date: {$lessonDate}")
            ->line("Time: {$lessonTime}")
            ->line("Instructor: {$instructorName}")
            ->line("Amount paid: {$amount}")
            ->line('')
            ->line('Your lesson is confirmed. Please arrive 5 minutes early.')
            ->line('If you need to make any changes, please contact us as soon as possible.')
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

    protected function getIntroLine(string $lessonDate, string $lessonTime, string $amount): string
    {
        $learnerName = $this->student->first_name.' '.$this->student->surname;

        if ($this->isBookedByContact) {
            return "We've received your payment of **{$amount}** for **{$learnerName}'s** driving lesson on **{$lessonDate}** at **{$lessonTime}**.";
        }

        return "We've received your payment of **{$amount}** for your driving lesson on **{$lessonDate}** at **{$lessonTime}**.";
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
