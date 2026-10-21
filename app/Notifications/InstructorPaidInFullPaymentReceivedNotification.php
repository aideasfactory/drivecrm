<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Enums\LessonStatus;
use App\Mail\RendersTemplatedMail;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the instructor a learner paid for a whole package in full, with every
 * booked lesson and the lesson fees they receive (each lesson's amount, which
 * is what its payout sends at sign-off). Excludes the booking and digital fees.
 */
class InstructorPaidInFullPaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Order $order,
        public Student $student
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
            EmailTemplateKey::InstructorPaidInFullPaymentReceived,
            [
                'student_name' => $this->studentName(),
                'package_name' => $order->package_name,
                'lessons_count' => $order->package_lessons_count,
                'lesson_fee' => $order->formatted_package_lesson_price,
                'amount' => $this->formatPence($this->lessonFeesPence()),
                'lessons_list' => $order->bookedLessonsList(),
            ],
        );
    }

    /**
     * Total lesson fees the instructor receives for the order's booked lessons.
     */
    protected function lessonFeesPence(): int
    {
        return (int) $this->order->lessons()
            ->where('status', '!=', LessonStatus::CANCELLED)
            ->sum('amount_pence');
    }

    protected function studentName(): string
    {
        return trim(($this->student->first_name ?? '').' '.($this->student->surname ?? ''));
    }

    protected function formatPence(int $pence): string
    {
        return '£'.number_format($pence / 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'student_name' => $this->studentName(),
            'amount' => $this->formatPence($this->lessonFeesPence()),
        ];
    }
}
