<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class RefundRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Lesson>  $paidLessons  Cancelled lessons that had been paid for.
     */
    public function __construct(
        public ?Student $student,
        public ?Instructor $instructor,
        public ?Order $order,
        public Collection $paidLessons,
        public string $reason,
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
        $studentName = $this->student
            ? trim($this->student->first_name.' '.$this->student->surname)
            : 'Unknown student';
        $instructorName = $this->instructor?->user?->name ?? 'Unknown instructor';
        $paymentMode = $this->order?->payment_mode?->value ?? 'unknown';

        $message = (new MailMessage)
            ->subject('Action Required: Refund for Cancelled Booking')
            ->greeting('Refund required')
            ->line('A booking has been cancelled and one or more **paid** lessons need a manual refund.')
            ->line('')
            ->line("**Student:** {$studentName}")
            ->line("**Instructor:** {$instructorName}")
            ->line('**Order:** #'.($this->order?->id ?? 'n/a'))
            ->line("**Payment mode:** {$paymentMode}")
            ->line('')
            ->line('**Paid lessons to refund:**');

        $totals = ['paid' => 0, 'lesson' => 0, 'booking_fee' => 0, 'digital_fee' => 0, 'test_pass_guarantee' => 0];
        foreach ($this->paidLessons as $lesson) {
            $breakdown = $this->paidBreakdown($lesson);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $breakdown[$key];
            }

            $message->line('• '.$this->formatLesson($lesson, $breakdown));
        }

        $refundPence = $totals['lesson'] + $totals['booking_fee'];

        $message->line('')
            ->line('**Total paid for cancelled lessons:** '.$this->formatPence($totals['paid']))
            ->line('Lessons: '.$this->formatPence($totals['lesson']))
            ->line('Booking fee: '.$this->formatPence($totals['booking_fee']))
            ->line('Digital fee (retained, not refunded): '.$this->formatPence($totals['digital_fee']));

        if ($totals['test_pass_guarantee'] > 0) {
            $message->line('Pass Your Test Guarantee (retained, not refunded): '.$this->formatPence($totals['test_pass_guarantee']));
        }

        $message->line('')
            ->line('**Amount to refund:** '.$this->formatPence($refundPence))
            ->line('')
            ->line('**Cancellation reason:**')
            ->line($this->reason)
            ->line('')
            ->line('Please action the refund manually in Stripe. No automatic refund has been issued.')
            ->salutation('— '.config('app.name'));

        return $message;
    }

    /**
     * What the student paid for a lesson, keyed for totalling.
     *
     * @return array{paid: int, lesson: int, booking_fee: int, digital_fee: int, test_pass_guarantee: int}
     */
    protected function paidBreakdown(Lesson $lesson): array
    {
        if ($this->order && ! $lesson->relationLoaded('order')) {
            $lesson->setRelation('order', $this->order);
        }

        $breakdown = $lesson->paymentBreakdown();

        return [
            'paid' => $breakdown['total_pence'],
            'lesson' => $breakdown['lesson_pence'],
            'booking_fee' => $breakdown['booking_fee_pence'],
            'digital_fee' => $breakdown['digital_fee_pence'],
            'test_pass_guarantee' => $breakdown['test_pass_guarantee_pence'],
        ];
    }

    /**
     * Format a single paid lesson with its date, amount paid and fee split.
     *
     * @param  array{paid: int, lesson: int, booking_fee: int, digital_fee: int, test_pass_guarantee: int}  $breakdown
     */
    protected function formatLesson(Lesson $lesson, array $breakdown): string
    {
        $date = $lesson->date?->format('l, j F Y') ?? 'Date unknown';
        $time = ($lesson->start_time && $lesson->end_time)
            ? ' at '.$lesson->start_time->format('H:i').' - '.$lesson->end_time->format('H:i')
            : '';

        $line = "{$date}{$time} — paid ".$this->formatPence($breakdown['paid']);

        if ($breakdown['booking_fee'] > 0 || $breakdown['digital_fee'] > 0 || $breakdown['test_pass_guarantee'] > 0) {
            $parts = [
                'lesson '.$this->formatPence($breakdown['lesson']),
                'booking fee '.$this->formatPence($breakdown['booking_fee']),
                'digital fee '.$this->formatPence($breakdown['digital_fee']),
            ];

            if ($breakdown['test_pass_guarantee'] > 0) {
                $parts[] = 'Pass Your Test Guarantee '.$this->formatPence($breakdown['test_pass_guarantee']);
            }

            $line .= ' ('.implode(', ', $parts).')';
        }

        return $line;
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
            'student_id' => $this->student?->id,
            'instructor_id' => $this->instructor?->id,
            'order_id' => $this->order?->id,
            'paid_lesson_ids' => $this->paidLessons->pluck('id')->all(),
            'reason' => $this->reason,
        ];
    }
}
