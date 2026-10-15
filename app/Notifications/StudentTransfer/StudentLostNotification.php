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

class StudentLostNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Student $student,
        public Instructor $sourceInstructor,
        public Instructor $destinationInstructor,
        public int $lessonsRemoved,
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
        $studentName = trim("{$this->student->first_name} {$this->student->surname}") ?: 'A student';
        $destinationName = $this->destinationInstructor->name ?? 'another instructor';

        return $this->templatedMail(
            EmailTemplateKey::InstructorStudentLost,
            [
                'recipient_name' => $this->sourceInstructor->first_name ?? 'there',
                'student_name' => $studentName,
                'destination_name' => $destinationName,
                'lessons_removed' => $this->lessonsRemoved,
                'app_name' => config('app.name'),
                'multiple_lessons' => $this->lessonsRemoved > 1,
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
            'source_instructor_id' => $this->sourceInstructor->id,
            'destination_instructor_id' => $this->destinationInstructor->id,
            'lessons_removed' => $this->lessonsRemoved,
        ];
    }
}
