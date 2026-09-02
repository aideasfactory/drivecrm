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

class LessonSignedOffNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public Instructor $instructor,
        public bool $isForInstructor = false
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
        $lessonDate = $this->lesson->date?->format('l, j F Y') ?? 'N/A';
        $lessonTimeLine = '';

        if ($this->lesson->start_time && $this->lesson->end_time) {
            $lessonTimeLine = 'Lesson time: '.$this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i');
        }

        if ($this->isForInstructor) {
            $studentName = trim(($this->student->first_name ?? '').' '.($this->student->surname ?? ''));

            return $this->templatedMail(
                EmailTemplateKey::InstructorLessonSignedOff,
                [
                    'recipient_name' => $this->instructor->user?->name ?? 'there',
                    'student_name' => $studentName,
                    'lesson_date' => $lessonDate,
                    'lesson_time_line' => $lessonTimeLine,
                    'app_name' => config('app.name'),
                ],
            );
        }

        $notesBlock = $this->lesson->summary
            ? "**Instructor Notes:**\n".$this->lesson->summary
            : '';

        return $this->templatedMail(
            EmailTemplateKey::LearnerLessonSignedOff,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->instructor->user?->name ?? 'your instructor',
                'lesson_date' => $lessonDate,
                'lesson_time_line' => $lessonTimeLine,
                'instructor_notes_block' => $notesBlock,
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
            'lesson_id' => $this->lesson->id,
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor->id,
        ];
    }
}
