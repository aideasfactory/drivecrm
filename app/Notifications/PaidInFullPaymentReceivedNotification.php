<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment confirmation for a package paid in full: the whole amount paid, its
 * cost breakdown and every booked lesson. Weekly payments use
 * LessonPaymentReceivedNotification instead.
 */
class PaidInFullPaymentReceivedNotification extends Notification implements ShouldQueue
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

        return $this->templatedMail(
            EmailTemplateKey::LearnerPaidInFullPaymentReceived,
            [
                'recipient_name' => $this->recipientName(),
                'learner_name' => trim(($this->student->first_name ?? '').' '.($this->student->surname ?? '')),
                'learner_first_name' => $this->student->first_name,
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'instructor_name' => $order->instructor?->user?->name ?? 'your instructor',
                ...$order->costBreakdownPlaceholders(),
                'total' => $order->formatted_amount_paid,
                'lessons_list' => $order->bookedLessonsList(),
                'booked_by_contact' => $this->isBookedByContact,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'amount' => $this->order->formatted_amount_paid,
        ];
    }
}
