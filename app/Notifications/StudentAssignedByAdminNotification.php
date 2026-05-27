<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAssignedByAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public Instructor $instructor,
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
        $instructorFirstName = $this->instructor->first_name ?? 'there';
        $studentName = trim("{$this->student->first_name} {$this->student->surname}")
            ?: ($this->student->email ?? 'A new student');

        return (new MailMessage)
            ->subject("New student assigned: {$studentName}")
            ->greeting("Hello {$instructorFirstName},")
            ->line("A new student, **{$studentName}**, has been assigned to you by an administrator.")
            ->line('Log in to view their details and get in touch to arrange their first lesson.')
            ->action('View student', url('/pupils'))
            ->salutation("Thanks,\nThe ".config('app.name').' Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor->id,
        ];
    }
}
