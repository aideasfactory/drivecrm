<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\LessonCancellationReason;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Student;
use App\Support\BookingPayments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Emails Head Office when paid lessons are cancelled. Nothing is refunded or
 * paid out automatically: Head Office contacts the pupil and instructor, then
 * refunds the pupil or pays the instructor by hand.
 */
class CancelledPaidLessonsReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Lesson>  $paidLessons  Cancelled lessons that had been paid for.
     * @param  array<int, int|null>  $hoursNotice  Hours' notice given for each lesson, keyed by lesson id (negative once it had started).
     */
    public function __construct(
        public ?Student $student,
        public ?Instructor $instructor,
        public ?Order $order,
        public Collection $paidLessons,
        public LessonCancellationReason $reasonCode,
        public ?string $details,
        public array $hoursNotice = [],
        public ?string $cancelledByName = null,
        public bool $guaranteeRemoved = false,
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
        $details = trim((string) $this->details);

        $message = (new MailMessage)
            ->subject('Action Required: Review Cancelled Paid Lesson(s)')
            ->greeting('Cancelled paid lessons to review')
            ->line('A booking has been cancelled and one or more **paid** lessons need a decision. Nothing has been refunded to the pupil and the instructor has not been paid for these lessons.')
            ->line('')
            ->line("**Student:** {$studentName}")
            ->line("**Instructor:** {$instructorName}")
            ->line('**Order:** #'.($this->order?->id ?? 'n/a'))
            ->line("**Payment mode:** {$paymentMode}")
            ->line('**Cancelled in the system by:** '.($this->cancelledByName ?? 'Unknown'))
            ->line('')
            ->line('**Reason:** '.$this->reasonCode->label())
            ->line('**Who cancelled:** '.$this->reasonCode->cancelledByLabel())
            ->line('**Details:** '.($details === '' ? 'None given' : $details))
            ->line('')
            ->line('**Paid lessons cancelled:**');

        $totals = ['paid' => 0, 'lesson' => 0, 'booking_fee' => 0, 'digital_fee' => 0, 'test_pass_guarantee' => 0];
        foreach ($this->paidLessons as $lesson) {
            $breakdown = $this->paidBreakdown($lesson);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $breakdown[$key];
            }

            $message->line('• '.$this->formatLesson($lesson, $breakdown).' — '.$this->formatNotice($lesson));
        }

        // A refund returns everything except the booking fee, which is kept.
        $refundPence = $totals['paid'] - $totals['booking_fee'];

        $message->line('')
            ->line('**Total paid for cancelled lessons:** '.$this->formatPence($totals['paid']))
            ->line('Lessons: '.$this->formatPence($totals['lesson']))
            ->line('Digital fee: '.$this->formatPence($totals['digital_fee']));

        if ($totals['test_pass_guarantee'] > 0) {
            $message->line('Pass Your Test Guarantee: '.$this->formatPence($totals['test_pass_guarantee']));
        }

        $message->line('Booking fee (retained, not refunded): '.$this->formatPence($totals['booking_fee']));

        if ($this->guaranteeRemoved) {
            $message->line('')
                ->line("The Pass Your Test Guarantee has been removed from the pupil's account. Re-grant it if the pupil is not refunded.");
        }

        $message->line('')
            ->line('**Amount to refund if the pupil is refunded:** '.$this->formatPence($refundPence))
            ->line('')
            ->line('**What to do:** contact the pupil and the instructor to confirm what happened. If the instructor cancelled, refund the pupil in Stripe. If the pupil cancelled with less than '.BookingPayments::lateCancellationHours().' hours\' notice, decide whether to refund the pupil or pay the instructor for the lesson.')
            ->line('')
            ->line('Nothing has been refunded or paid out automatically.')
            ->salutation('— '.config('app.name'));

        return $message;
    }

    /**
     * How much notice was given for a lesson, flagged when it was late.
     */
    protected function formatNotice(Lesson $lesson): string
    {
        $hours = $this->hoursNotice[$lesson->id] ?? null;

        if ($hours === null) {
            return 'notice unknown';
        }

        if ($hours < 0) {
            return '**LATE** — cancelled after the lesson started';
        }

        $notice = $hours === 1 ? "1 hour's notice" : "{$hours} hours' notice";

        return $hours < BookingPayments::lateCancellationHours() ? "**LATE** — {$notice}" : $notice;
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
            'reason_code' => $this->reasonCode->value,
            'details' => $this->details,
            'hours_notice' => $this->hoursNotice,
        ];
    }
}
