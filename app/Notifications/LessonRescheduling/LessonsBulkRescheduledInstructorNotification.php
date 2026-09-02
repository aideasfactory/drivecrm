<?php

declare(strict_types=1);

namespace App\Notifications\LessonRescheduling;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Instructor;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonsBulkRescheduledInstructorNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Instructor $instructor,
        public ?Student $student,
        public int $totalLessons,
        public string $newStartDate,
        public string $newStartTime,
        public string $newEndTime,
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
        $studentName = $this->student
            ? trim("{$this->student->first_name} {$this->student->surname}")
            : 'a student';
        $startDate = Carbon::parse($this->newStartDate);
        $lessonWord = $this->totalLessons === 1 ? 'lesson' : 'lessons';

        return $this->templatedMail(
            EmailTemplateKey::InstructorLessonsBulkRescheduled,
            [
                'recipient_name' => $this->instructor->first_name ?? 'there',
                'student_name' => $studentName,
                'total_lessons' => $this->totalLessons,
                'lesson_word' => $lessonWord,
                'day_of_week' => $startDate->format('l'),
                'time' => $this->newStartTime.' – '.$this->newEndTime,
                'start_date' => $startDate->format('l, j F Y'),
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
            'instructor_id' => $this->instructor->id,
            'student_id' => $this->student?->id,
            'total_lessons' => $this->totalLessons,
            'new_start_date' => $this->newStartDate,
            'new_start_time' => $this->newStartTime,
            'new_end_time' => $this->newEndTime,
        ];
    }
}
