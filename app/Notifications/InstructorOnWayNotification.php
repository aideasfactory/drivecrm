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

class InstructorOnWayNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

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
        return $this->templatedMail(
            EmailTemplateKey::LearnerInstructorOnWay,
            [
                'recipient_name' => $this->student->getBookerDetails()['first_name'] ?? 'there',
                'instructor_name' => $this->instructor->name ?? 'Your instructor',
                'lesson_when' => $this->lessonWhen(),
                'app_name' => config('app.name'),
            ],
        );
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
