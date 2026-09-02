<?php

declare(strict_types=1);

namespace App\Notifications\StudentTransfer;

use App\Enums\EmailTemplateKey;
use App\Mail\RendersTemplatedMail;
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
    use RendersTemplatedMail;

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
        $studentName = trim("{$this->student->first_name} {$this->student->surname}") ?: 'A new student';
        $sourceName = $this->sourceInstructor->name ?? 'another instructor';

        return $this->templatedMail(
            EmailTemplateKey::InstructorStudentGained,
            [
                'recipient_name' => $this->destinationInstructor->first_name ?? 'there',
                'student_name' => $studentName,
                'source_name' => $sourceName,
                'lessons_block' => $this->lessonsBlock(),
                'app_name' => config('app.name'),
            ],
        );
    }

    protected function lessonsBlock(): string
    {
        $movedCount = $this->movedLessons->count();
        $clashCount = $this->clashingLessons->count();

        if ($movedCount === 0) {
            return 'No future lessons were on the previous instructor’s diary, so nothing has been added to yours yet. The student will book new lessons with you as normal.';
        }

        $lines = [];
        $lines[] = $movedCount === 1
            ? 'The following lesson has been added to your diary at its existing date and time:'
            : "The following {$movedCount} lessons have been added to your diary at their existing dates and times:";

        $clashIds = $this->clashingLessons->pluck('id')->all();
        $sortedLessons = $this->movedLessons->sortBy(fn (Lesson $lesson) => ($lesson->date?->format('Y-m-d') ?? '9999-12-31').' '.($lesson->start_time?->format('H:i') ?? '23:59'));

        foreach ($sortedLessons as $lesson) {
            $dateFormatted = $lesson->date?->format('l, j F Y') ?? 'Unknown date';
            $timeFormatted = $lesson->start_time && $lesson->end_time
                ? $lesson->start_time->format('H:i').' – '.$lesson->end_time->format('H:i')
                : '';
            $isClash = in_array($lesson->id, $clashIds, true);

            if ($isClash) {
                $lines[] = "⚠️ **{$dateFormatted} at {$timeFormatted} — clashes with your existing diary**";
            } else {
                $lines[] = "• {$dateFormatted} at {$timeFormatted}";
            }
        }

        if ($clashCount > 0) {
            $clashWord = $clashCount === 1 ? 'clash' : 'clashes';
            $lines[] = '';
            $lines[] = "⚠️ **{$clashCount} {$clashWord} flagged above.** Please review your diary and rebook the affected lessons at alternative times that suit you and the student.";
        }

        return implode("\n", $lines);
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
