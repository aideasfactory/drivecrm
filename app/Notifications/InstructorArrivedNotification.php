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

class InstructorArrivedNotification extends Notification implements ShouldQueue
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
            ->subject('Your instructor has arrived')
            ->greeting("Hello {$studentName},")
            ->line("**{$instructorName}** has arrived for your driving lesson and is waiting for you at the pickup point.")
            ->line('Please head out when you are ready.')
            ->salutation("Have a great lesson,\nThe ".config('app.name').' Team');
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
            'notification_type' => 'arrived',
        ];
    }
}
