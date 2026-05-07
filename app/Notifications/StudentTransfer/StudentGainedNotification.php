<?php

declare(strict_types=1);

namespace App\Notifications\StudentTransfer;

use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentGainedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Lesson>  $movedLessons
     * @param  Collection<int, Lesson>  $clashingLessons
     */
    public function __construct(
        public Student $student,
        public Instructor $sourceInstructor,
        public Instructor $destinationInstructor,
        public Collection $movedLessons,
        public Collection $clashingLessons,
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
        $instructorFirstName = $this->destinationInstructor->first_name ?? 'there';
        $studentName = trim("{$this->student->first_name} {$this->student->surname}") ?: 'A new student';
        $sourceName = $this->sourceInstructor->name ?? 'another instructor';
        $movedCount = $this->movedLessons->count();
        $clashCount = $this->clashingLessons->count();

        $message = (new MailMessage)
            ->subject("New student assigned: {$studentName}")
            ->greeting("Hello {$instructorFirstName},")
            ->line("**{$studentName}** has been transferred to you from **{$sourceName}**.");

        if ($movedCount === 0) {
            $message->line('No future lessons were on the previous instructor’s diary, so nothing has been added to yours yet. The student will book new lessons with you as normal.');
        } else {
            $lessonsLine = $movedCount === 1
                ? '1 lesson has been added to your diary at its existing date and time.'
                : "{$movedCount} lessons have been added to your diary at their existing dates and times.";
            $message->line($lessonsLine);
        }

        if ($clashCount > 0) {
            $clashWord = $clashCount === 1 ? 'clash' : 'clashes';
            $message->line('')
                ->line("⚠️ **{$clashCount} {$clashWord} detected with your existing diary:**");

            foreach ($this->clashingLessons as $clash) {
                $dateFormatted = $clash->date?->format('l, j F Y') ?? 'Unknown date';
                $timeFormatted = $clash->start_time && $clash->end_time
                    ? $clash->start_time->format('H:i').' – '.$clash->end_time->format('H:i')
                    : '';
                $message->line("• {$dateFormatted} at {$timeFormatted}");
            }

            $message->line('')
                ->line('Please review your diary and rebook these lessons at alternative times that suit you and the student.');
        }

        $message->line('Payment for any future lessons will be sent to your Stripe account once the lesson has been signed off.')
            ->salutation("Thanks,\nThe ".config('app.name').' Team');

        return $message;
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
            'moved_lesson_ids' => $this->movedLessons->pluck('id')->all(),
            'clashing_lesson_ids' => $this->clashingLessons->pluck('id')->all(),
        ];
    }
}
