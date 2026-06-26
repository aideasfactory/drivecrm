<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Lesson>  $lessons  The cancelled lessons.
     */
    public function __construct(
        public Student $student,
        public ?Instructor $instructor,
        public Collection $lessons,
        public string $reason,
        public bool $refundRequired,
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
        $studentName = $this->student->first_name ?? 'there';
        $instructorName = $this->instructor?->user?->name ?? 'your instructor';
        $count = $this->lessons->count();
        $lessonWord = $count === 1 ? 'lesson has' : 'lessons have';

        $message = (new MailMessage)
            ->subject($count === 1 ? 'Your Driving Lesson Has Been Cancelled' : 'Your Driving Lessons Have Been Cancelled')
            ->greeting("Hello {$studentName},")
            ->line("We're letting you know that the following {$lessonWord} been cancelled by **{$instructorName}**:");

        foreach ($this->lessons as $lesson) {
            $message->line('• '.$this->formatLesson($lesson));
        }

        $message->line('')
            ->line('**Reason:**')
            ->line($this->reason)
            ->line('');

        if ($this->refundRequired) {
            $message->line('Any payments you have already made for these lessons will be refunded — our head office will be in touch about this shortly.');
        } else {
            $message->line('There is nothing further you need to do, and you will not be charged for these lessons.');
        }

        $message->line('If you have any questions, please get in touch with your instructor.')
            ->salutation("Kind regards,\nThe ".config('app.name').' Team');

        return $message;
    }

    /**
     * Format a single cancelled lesson as a readable date/time line.
     */
    protected function formatLesson(Lesson $lesson): string
    {
        $date = $lesson->date?->format('l, j F Y') ?? 'Date to be confirmed';

        if ($lesson->start_time && $lesson->end_time) {
            return $date.' at '.$lesson->start_time->format('H:i').' - '.$lesson->end_time->format('H:i');
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor?->id,
            'lesson_ids' => $this->lessons->pluck('id')->all(),
            'reason' => $this->reason,
            'refund_required' => $this->refundRequired,
        ];
    }
}
