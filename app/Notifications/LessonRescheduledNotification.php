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

class LessonRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public Instructor $instructor,
        public string $oldDate,
        public string $oldStartTime,
        public string $oldEndTime,
        public ?string $notes = null
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
        $instructorName = $this->instructor->user?->name ?? 'your instructor';

        $oldDateFormatted = \Carbon\Carbon::parse($this->oldDate)->format('l, j F Y');
        $oldTime = $this->oldStartTime.' - '.$this->oldEndTime;

        $newDateFormatted = $this->lesson->date?->format('l, j F Y') ?? 'N/A';
        $newTime = null;
        if ($this->lesson->start_time && $this->lesson->end_time) {
            $newTime = $this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i');
        }

        $message = (new MailMessage)
            ->subject('Your Driving Lesson Has Been Rescheduled')
            ->greeting("Hello {$studentName}!")
            ->line("Your driving lesson with **{$instructorName}** has been rescheduled.")
            ->line('')
            ->line('**Previous:**')
            ->line("{$oldDateFormatted} at {$oldTime}")
            ->line('')
            ->line('**New:**')
            ->line("{$newDateFormatted}".($newTime ? " at {$newTime}" : ''));

        if ($this->notes) {
            $message->line('')
                ->line('**Notes from your instructor:**')
                ->line($this->notes);
        }

        $message->line('')
            ->line('If this new time does not work for you, please contact your instructor to arrange an alternative.')
            ->salutation("Safe driving,\nThe ".config('app.name').' Team');

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lesson_id' => $this->lesson->id,
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor->id,
            'old_date' => $this->oldDate,
            'old_start_time' => $this->oldStartTime,
            'old_end_time' => $this->oldEndTime,
        ];
    }
}
