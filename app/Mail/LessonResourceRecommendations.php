<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class LessonResourceRecommendations extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, resource>  $resources
     */
    public function __construct(
        public Lesson $lesson,
        public Student $student,
        public Collection $resources
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recommended resources from your driving lesson',
        );
    }

    public function content(): Content
    {
        $resourceLinks = $this->resources->map(fn (Resource $resource) => [
            'title' => $resource->title,
            'description' => $resource->description,
            'type' => $resource->isVideo() ? 'Video' : ($resource->isPdf() ? 'PDF' : 'File'),
            'url' => URL::signedRoute('resources.email-view', [
                'resource' => $resource->id,
            ], now()->addDays(7)),
        ]);

        return new Content(
            view: 'emails.lesson-resource-recommendations',
            with: [
                'studentName' => $this->student->first_name ?? 'there',
                'instructorName' => $this->lesson->instructor?->user?->name ?? 'your instructor',
                'lessonDate' => $this->lesson->date?->format('l, j F Y') ?? 'your recent lesson',
                'summaryExcerpt' => str($this->lesson->summary ?? '')->limit(200)->toString(),
                'resourceLinks' => $resourceLinks,
            ],
        );
    }
}
