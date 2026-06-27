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

class InstructorOnWayNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Lesson $lesson,
        public Instructor $instructor,
        public Student $student,
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
        $studentName = $this->student->getBookerDetails()['first_name'] ?? 'there';
        $instructorName = $this->instructor->name ?? 'Your instructor';

        return (new MailMessage)
            ->subject('Your instructor is on the way')
            ->greeting("Hello {$studentName},")
            ->line("**{$instructorName}** is on their way to your driving lesson{$this->lessonWhen()}.")
            ->line('Please be ready at your agreed pickup point.')
            ->salutation("See you soon,\nThe ".config('app.name').' Team');
    }

    /**
     * A readable " on Monday, 1 June at 14:00" suffix, or empty string when unknown.
     */
    protected function lessonWhen(): string
    {
        $date = $this->lesson->date?->format('l, j F');

        if ($date && $this->lesson->start_time) {
            return ' on '.$date.' at '.$this->lesson->start_time->format('H:i');
        }

        return $date ? ' on '.$date : '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lesson_id' => $this->lesson->id,
            'instructor_id' => $this->instructor->id,
            'student_id' => $this->student->id,
            'notification_type' => 'on_way',
        ];
    }
}
