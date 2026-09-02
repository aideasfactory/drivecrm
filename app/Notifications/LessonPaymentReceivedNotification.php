<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\LessonPayment;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonPaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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

        return $this->templatedMail(
            EmailTemplateKey::LearnerLessonPaymentReceived,
            [
                'recipient_name' => $this->recipientName(),
                'intro' => $this->introLine($lessonDate, $lessonTime, $amount),
                'package_name' => $order->package_name,
                'lesson_date' => $lessonDate,
                'lesson_time' => $lessonTime,
                'instructor_name' => $instructorName,
                'amount' => $amount,
            ],
        );
    }

    protected function recipientName(): string
    {
        if ($this->isBookedByContact) {
            return $this->student->contact_first_name ?? 'there';
        }

        return $this->student->first_name ?? 'there';
    }

    protected function introLine(string $lessonDate, string $lessonTime, string $amount): string
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
