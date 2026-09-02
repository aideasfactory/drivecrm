<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAssignedByAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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
        $studentName = trim("{$this->student->first_name} {$this->student->surname}")
            ?: ($this->student->email ?? 'A new student');

        return $this->templatedMail(
            EmailTemplateKey::InstructorStudentAssigned,
            [
                'recipient_name' => $this->instructor->first_name ?? 'there',
                'student_name' => $studentName,
                'app_name' => config('app.name'),
            ],
            url('/pupils'),
        );
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
