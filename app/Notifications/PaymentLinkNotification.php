<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Order;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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

        $firstLessonLine = $firstLesson
            ? 'First lesson: '.Carbon::parse($firstLesson->date)->format('l, F j, Y')
            : '';

        $bookedForLine = '';
        if ($this->isBookedByContact) {
            $learnerName = $this->student->first_name.' '.$this->student->surname;
            $bookedForLine = "\nThis booking was made for: **{$learnerName}**";
        }

        return $this->templatedMail(
            EmailTemplateKey::LearnerPaymentLink,
            [
                'recipient_name' => $this->recipientName(),
                'intro' => $this->introLine(),
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'instructor_name' => $instructor->user->name,
                'total' => $totalFormatted,
                'first_lesson_line' => $firstLessonLine,
                'booked_for_line' => $bookedForLine,
            ],
            $this->checkoutUrl,
        );
    }

    protected function recipientName(): string
    {
        if ($this->isBookedByContact) {
            return $this->student->contact_first_name ?? 'there';
        }

        return $this->student->first_name ?? 'there';
    }

    protected function introLine(): string
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
