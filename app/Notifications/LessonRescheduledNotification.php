<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonRescheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RendersTemplatedMail;

    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public Instructor $instructor,
        public string $oldDate,
        public string $oldStartTime,
        public string $oldEndTime,
        public ?string $notes = null
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
        $oldDateFormatted = Carbon::parse($this->oldDate)->format('l, j F Y');
        $oldTime = $this->oldStartTime.' - '.$this->oldEndTime;

        $newDateFormatted = $this->lesson->date?->format('l, j F Y') ?? 'N/A';
        $newTime = null;
        if ($this->lesson->start_time && $this->lesson->end_time) {
            $newTime = $this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i');
        }

        $notesBlock = $this->notes
            ? "\n**Notes from your instructor:**\n".$this->notes
            : '';

        return $this->templatedMail(
            EmailTemplateKey::LearnerLessonRescheduled,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->instructor->user?->name ?? 'your instructor',
                'old_when' => $oldDateFormatted.' at '.$oldTime,
                'new_when' => $newDateFormatted.($newTime ? ' at '.$newTime : ''),
                'notes_block' => $notesBlock,
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
            'old_date' => $this->oldDate,
            'old_start_time' => $this->oldStartTime,
            'old_end_time' => $this->oldEndTime,
        ];
    }
}
