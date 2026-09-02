<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
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
    use Queueable;
    use RendersTemplatedMail;
    use SerializesModels;

    private ?RenderedEmailTemplate $renderedCache = null;

    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public Instructor $instructor
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->rendered()->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.templated',
            with: $this->templatedViewData($this->rendered()),
        );
    }

    private function rendered(): RenderedEmailTemplate
    {
        $lessonTime = $this->lesson->start_time?->format('H:i') && $this->lesson->end_time?->format('H:i')
            ? ' ('.$this->lesson->start_time->format('H:i').' - '.$this->lesson->end_time->format('H:i').')'
            : '';

        return $this->renderedCache ??= $this->renderedTemplate(
            EmailTemplateKey::LearnerLessonFeedbackRequest,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->instructor->user?->name ?? 'your instructor',
                'lesson_date' => $this->lesson->date?->format('l, j F Y') ?? 'your recent lesson',
                'lesson_time' => $lessonTime,
            ],
        );
    }
}
