<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LessonFeedbackRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public Instructor $instructor
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How was your driving lesson?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lesson-feedback-request',
            with: [
                'studentName' => $this->student->first_name ?? 'there',
                'instructorName' => $this->instructor->user?->name ?? 'your instructor',
                'lessonDate' => $this->lesson->date?->format('l, j F Y') ?? 'your recent lesson',
                'lessonTime' => $this->lesson->start_time?->format('H:i') && $this->lesson->end_time?->format('H:i')
                    ? $this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i')
                    : null,
            ],
        );
    }
}
