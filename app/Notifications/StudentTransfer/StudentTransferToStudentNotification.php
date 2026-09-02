<?php

declare(strict_types=1);

namespace App\Notifications\StudentTransfer;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Instructor;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentTransferToStudentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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
        return $this->templatedMail(
            EmailTemplateKey::LearnerStudentTransfer,
            [
                'recipient_name' => $this->student->first_name ?: 'there',
                'instructor_name' => $this->destinationInstructor->name ?? 'your new instructor',
                'app_name' => config('app.name'),
            ],
        );
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
