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

        $total = 0;
        foreach ($this->paidLessons as $lesson) {
            $total += (int) $lesson->amount_pence;
            $message->line('• '.$this->formatLesson($lesson));
        }

        $message->line('')
            ->line('**Total paid for cancelled lessons:** £'.number_format($total / 100, 2))
            ->line('')
            ->line('**Cancellation reason:**')
            ->line($this->reason)
            ->line('')
            ->line('Please action the refund manually in Stripe. No automatic refund has been issued.')
            ->salutation('— '.config('app.name'));

        return $message;
    }

    /**
     * Format a single paid lesson with its date and amount.
     */
    protected function formatLesson(Lesson $lesson): string
    {
        $date = $lesson->date?->format('l, j F Y') ?? 'Date unknown';
        $time = ($lesson->start_time && $lesson->end_time)
            ? ' at '.$lesson->start_time->format('H:i').' - '.$lesson->end_time->format('H:i')
            : '';
        $amount = '£'.number_format(((int) $lesson->amount_pence) / 100, 2);

        return "{$date}{$time} — {$amount}";
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
