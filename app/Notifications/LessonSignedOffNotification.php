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

class LessonSignedOffNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        $lessonTime = null;

        if ($this->lesson->start_time && $this->lesson->end_time) {
            $lessonTime = $this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i');
        }

        if ($this->isForInstructor) {
            return $this->buildInstructorMail($lessonDate, $lessonTime);
        }

        return $this->buildStudentMail($lessonDate, $lessonTime);
    }

    protected function buildStudentMail(string $lessonDate, ?string $lessonTime): MailMessage
    {
        $instructorName = $this->instructor->user?->name ?? 'your instructor';
        $studentName = $this->student->first_name ?? 'there';

        $message = (new MailMessage)
            ->subject('Your Driving Lesson Has Been Signed Off')
            ->greeting("Hello {$studentName}!")
            ->line("Great news! Your driving lesson on **{$lessonDate}** has been signed off by {$instructorName}.");

        if ($lessonTime) {
            $message->line("Lesson time: {$lessonTime}");
        }

        if ($this->lesson->summary) {
            $message->line('**Instructor Notes:**')
                ->line($this->lesson->summary);
        }

        $message->line('')
            ->line('Keep up the great work on your driving journey!')
            ->salutation("Safe driving,\nThe ".config('app.name').' Team');

        return $message;
    }

    protected function buildInstructorMail(string $lessonDate, ?string $lessonTime): MailMessage
    {
        $instructorName = $this->instructor->user?->name ?? 'there';
        $studentName = trim(($this->student->first_name ?? '').' '.($this->student->surname ?? ''));

        $message = (new MailMessage)
            ->subject('Lesson Signed Off — '.$studentName)
            ->greeting("Hello {$instructorName}!")
            ->line("You have signed off the lesson with **{$studentName}** on **{$lessonDate}**.");

        if ($lessonTime) {
            $message->line("Lesson time: {$lessonTime}");
        }

        $message->line('The payout for this lesson has been initiated to your account.')
            ->salutation("Thanks,\nThe ".config('app.name').' Team');

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
        ];
    }
}
