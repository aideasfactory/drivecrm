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

    /**
     * @param  list<string>  $missedLessons  Past lessons Head Office marked as missed ("Sat 21 Nov 2026, 08:00–10:00")
     */
    public function __construct(
        public Student $student,
        public Instructor $sourceInstructor,
        public Instructor $destinationInstructor,
        public int $lessonsRemoved,
        public array $missedLessons = [],
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
                'missed_count' => count($this->missedLessons),
                'multiple_missed' => count($this->missedLessons) > 1,
                'missed_list' => collect($this->missedLessons)->map(fn (string $lesson): string => "• {$lesson}")->implode("\n"),
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
            'missed_lessons' => $this->missedLessons,
        ];
    }
}
