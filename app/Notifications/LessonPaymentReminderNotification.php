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

class LessonPaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    /**
     * @param  array{lesson: int, booking_fee: int, digital_fee: int}|null  $breakdown
     */
    public function __construct(
        public LessonPayment $lessonPayment,
        public Student $student,
        public string $hostedInvoiceUrl,
        public bool $isBookedByContact,
        public ?array $breakdown = null
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

        return $this->templatedMail(
            EmailTemplateKey::LearnerLessonPaymentReminder,
            [
                'recipient_name' => $this->recipientName(),
                'intro' => $this->introLine($lessonDate, $lessonTime),
                'package_name' => $order->package_name,
                'lesson_date' => $lessonDate,
                'lesson_time' => $lessonTime,
                'cost_breakdown' => $this->costBreakdown(),
                'amount' => $amount,
            ],
            $this->hostedInvoiceUrl,
        );
    }

    protected function costBreakdown(): string
    {
        $lines = $this->breakdownLines();

        return $lines === [] ? '' : implode("\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    protected function breakdownLines(): array
    {
        if (! is_array($this->breakdown)) {
            return [];
        }

        $lesson = (int) ($this->breakdown['lesson'] ?? 0);
        $bookingFee = (int) ($this->breakdown['booking_fee'] ?? 0);
        $digitalFee = (int) ($this->breakdown['digital_fee'] ?? 0);

        if ($bookingFee <= 0 && $digitalFee <= 0) {
            return [];
        }

        $lines = ['**Cost breakdown:**'];

        if ($lesson > 0) {
            $lines[] = 'Lesson cost: '.$this->formatPence($lesson);
        }

        if ($bookingFee > 0) {
            $lines[] = 'Booking fee (weekly instalment): '.$this->formatPence($bookingFee);
        }

        if ($digitalFee > 0) {
            $lines[] = 'Digital services fee (weekly instalment): '.$this->formatPence($digitalFee);
        }

        $lines[] = '';

        return $lines;
    }

    protected function formatPence(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    protected function recipientName(): string
    {
        if ($this->isBookedByContact) {
            return $this->student->contact_first_name ?? 'there';
        }

        return $this->student->first_name ?? 'there';
    }

    protected function introLine(string $lessonDate, string $lessonTime): string
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
