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

class BankedLessonRebookedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public ?Instructor $instructor,
        public string $packageName,
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
        $newDate = $this->lesson->date?->format('l, j F Y') ?? 'N/A';
        $newTime = $this->lesson->start_time && $this->lesson->end_time
            ? $this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i')
            : null;

        return $this->templatedMail(
            EmailTemplateKey::LearnerBankedLessonRebooked,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->instructor?->user?->name ?? 'your instructor',
                'new_when' => $newDate.($newTime ? ' at '.$newTime : ''),
                'new_date' => $newDate,
                'new_time' => $newTime,
                'package_name' => $this->packageName,
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
            'instructor_id' => $this->instructor?->id,
        ];
    }
}
