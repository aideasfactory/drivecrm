<?php

declare(strict_types=1);

namespace App\Notifications\StudentTransfer;

use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentTransferToStudentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public Instructor $destinationInstructor,
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
        $studentName = $this->student->first_name ?: 'there';
        $instructorName = $this->destinationInstructor->name ?? 'your new instructor';

        return (new MailMessage)
            ->subject('Your driving lessons have moved to a new instructor')
            ->greeting("Hello {$studentName},")
            ->line("Your driving lessons have been moved to **{$instructorName}**.")
            ->line('Any future lessons you already had booked have been transferred into their diary at the same dates and times.')
            ->line("{$instructorName} will be in touch shortly to introduce themselves.")
            ->line('If you have any questions, please reply to this email.')
            ->salutation("Safe driving,\nThe ".config('app.name').' Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'destination_instructor_id' => $this->destinationInstructor->id,
        ];
    }
}
