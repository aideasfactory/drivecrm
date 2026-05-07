<?php

declare(strict_types=1);

namespace App\Notifications\LessonRescheduling;

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
        $instructorFirstName = $this->instructor->first_name ?? 'there';
        $studentName = $this->student
            ? trim("{$this->student->first_name} {$this->student->surname}")
            : 'a student';
        $startDate = Carbon::parse($this->newStartDate);
        $dayOfWeek = $startDate->format('l');
        $startDateFormatted = $startDate->format('l, j F Y');
        $time = $this->newStartTime.' – '.$this->newEndTime;

        $lessonWord = $this->totalLessons === 1 ? 'lesson' : 'lessons';

        return (new MailMessage)
            ->subject("Lessons rescheduled for {$studentName}")
            ->greeting("Hello {$instructorFirstName},")
            ->line("You have rescheduled **{$this->totalLessons}** upcoming {$lessonWord} for **{$studentName}**.")
            ->line("New schedule: **{$dayOfWeek}s at {$time}**, starting **{$startDateFormatted}**.")
            ->line('The student has been notified by email.')
            ->salutation("Thanks,\nThe ".config('app.name').' Team');
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
