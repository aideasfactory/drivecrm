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

class LessonsBulkRescheduledStudentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public Instructor $instructor,
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
        $studentName = $this->student->first_name ?: 'there';
        $instructorName = $this->instructor->user?->name ?? 'your instructor';
        $startDate = Carbon::parse($this->newStartDate);
        $dayOfWeek = $startDate->format('l');
        $startDateFormatted = $startDate->format('l, j F Y');
        $time = $this->newStartTime.' – '.$this->newEndTime;

        $lessonWord = $this->totalLessons === 1 ? 'lesson' : 'lessons';

        return (new MailMessage)
            ->subject('Your driving lessons have been rescheduled')
            ->greeting("Hello {$studentName},")
            ->line("Your upcoming {$lessonWord} with **{$instructorName}** have been rescheduled.")
            ->line("From **{$startDateFormatted}**, you will now have your lessons on **{$dayOfWeek}s at {$time}**.")
            ->line("Total {$lessonWord} moved: **{$this->totalLessons}**.")
            ->line('If this new schedule does not work for you, please contact your instructor to arrange alternatives.')
            ->salutation("Safe driving,\nThe ".config('app.name').' Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'instructor_id' => $this->instructor->id,
            'total_lessons' => $this->totalLessons,
            'new_start_date' => $this->newStartDate,
            'new_start_time' => $this->newStartTime,
            'new_end_time' => $this->newEndTime,
        ];
    }
}
