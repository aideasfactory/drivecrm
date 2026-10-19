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

/**
 * Booking confirmation for a cash order: the learner pays the instructor the
 * lesson price in cash at each lesson, with no booking or digital fee.
 */
class CashBookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Order $order,
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
        $order = $this->order;
        $firstLesson = $order->lessons()->orderBy('date')->first();

        return $this->templatedMail(
            EmailTemplateKey::LearnerCashBookingConfirmed,
            [
                'recipient_name' => $this->recipientName(),
                'learner_name' => trim(($this->student->first_name ?? '').' '.($this->student->surname ?? '')),
                'learner_first_name' => $this->student->first_name,
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'instructor_name' => $order->instructor?->user?->name ?? 'your instructor',
                'first_lesson_date' => $firstLesson ? Carbon::parse($firstLesson->date)->format('l, F j, Y') : '',
                'lesson_price' => $order->formatted_package_lesson_price,
                'total' => $order->formatted_amount_paid,
                'app_name' => config('app.name'),
                'booked_by_contact' => $this->isBookedByContact,
            ],
            url('/get-app'),
        );
    }

    protected function recipientName(): string
    {
        if ($this->isBookedByContact) {
            return $this->student->contact_first_name ?? 'there';
        }

        return $this->student->first_name ?? 'there';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'package_name' => $this->order->package_name,
            'lessons_count' => $this->order->package_lessons_count,
        ];
    }
}
