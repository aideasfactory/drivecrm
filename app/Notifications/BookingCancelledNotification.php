<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
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
    use RendersTemplatedMail;

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
        $count = $this->lessons->count();
        $lessonWord = $count === 1 ? 'lesson has' : 'lessons have';
        $lessonNoun = $count === 1 ? 'Lesson Has' : 'Lessons Have';

        $lessonList = $this->lessons
            ->map(fn (Lesson $lesson): string => '• '.$this->formatLesson($lesson))
            ->implode("\n");

        $refundLine = $this->refundRequired
            ? 'Any payments you have already made for these lessons will be refunded — our head office will be in touch about this shortly.'
            : 'There is nothing further you need to do, and you will not be charged for these lessons.';

        return $this->templatedMail(
            EmailTemplateKey::LearnerBookingCancelled,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->instructor?->user?->name ?? 'your instructor',
                'lesson_word' => $lessonWord,
                'lesson_noun' => $lessonNoun,
                'lesson_list' => $lessonList,
                'reason' => $this->reason,
                'refund_line' => $refundLine,
                'app_name' => config('app.name'),
            ],
        );
    }

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
