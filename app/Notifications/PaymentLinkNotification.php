<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Order;
use App\Models\Student;
use App\Support\BookingPayments;
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
        public bool $isBookedByContact,
        public bool $isBookedByStaff = false
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
        $firstWeeklyPayment = $order->isWeekly() ? $order->firstLessonPayment() : null;
        $totalFormatted = $firstWeeklyPayment
            ? '£'.number_format($firstWeeklyPayment->amount_pence / 100, 2)
            : $order->formatted_amount_paid;
        $costBreakdown = match (true) {
            $firstWeeklyPayment !== null => implode("\n", $firstWeeklyPayment->costBreakdownLines()),
            $order->hasFees() => implode("\n", $order->costBreakdownLines()),
            default => '',
        };

        return $this->templatedMail(
            EmailTemplateKey::LearnerPaymentLink,
            [
                'recipient_name' => $this->recipientName(),
                'learner_name' => trim(($this->student->first_name ?? '').' '.($this->student->surname ?? '')),
                'learner_first_name' => $this->student->first_name,
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'instructor_name' => $instructor->user->name,
                'cost_breakdown' => $costBreakdown,
                'total' => $totalFormatted,
                'first_lesson_date' => $firstLesson ? Carbon::parse($firstLesson->date)->format('l, F j, Y') : '',
                'pay_by' => $order->payment_hold_expires_at
                    ? BookingPayments::formatDeadline($order->payment_hold_expires_at)
                    : '',
                'weekly_payment_due_hours' => BookingPayments::weeklyPaymentDueHoursBeforeLesson(),
                'booked_by_contact' => $this->isBookedByContact,
                'booked_by_staff' => $this->isBookedByStaff,
                'pay_weekly' => $firstWeeklyPayment !== null,
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
