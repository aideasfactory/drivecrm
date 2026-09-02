<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class LessonResourcesAssigned extends Mailable
{
    use Queueable;
    use RendersTemplatedMail;
    use SerializesModels;

    private ?RenderedEmailTemplate $renderedCache = null;

    public function __construct(
        public Lesson $lesson,
        public Student $student
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
        return $this->renderedCache ??= $this->renderedTemplate(
            EmailTemplateKey::LearnerLessonResourcesAssigned,
            [
                'recipient_name' => $this->student->first_name ?? 'there',
                'instructor_name' => $this->lesson->instructor?->user?->name ?? 'your instructor',
                'lesson_date' => $this->lesson->date?->format('l, j F Y') ?? 'your upcoming lesson',
                'resource_list' => $this->resourceList(),
            ],
        );
    }

    private function resourceList(): string
    {
        return $this->lesson->resources
            ->map(function (Resource $resource): string {
                $type = $resource->isVideoLink() ? 'Video' : ($resource->isPdf() ? 'PDF' : 'File');
                $url = URL::signedRoute('resources.email-view', [
                    'resource' => $resource->id,
                ], now()->addDays(7));
                $description = $resource->description
                    ? "\n".$resource->description
                    : '';

                return "**{$type}: {$resource->title}**{$description}\n[View resource]({$url})";
            })
            ->implode("\n\n");
    }
}
