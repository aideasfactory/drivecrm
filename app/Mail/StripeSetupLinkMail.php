<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Models\Instructor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StripeSetupLinkMail extends Mailable
{
    use Queueable;
    use RendersTemplatedMail;
    use SerializesModels;

    private ?RenderedEmailTemplate $renderedCache = null;

    public function __construct(
        public Instructor $instructor,
        public string $setupUrl,
        public int $expiresInDays,
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
            EmailTemplateKey::InstructorStripeSetupLink,
            [
                'recipient_name' => $this->firstName(),
                'app_name' => config('app.name'),
                'setup_url' => $this->setupUrl,
                'expires_in_days' => (string) $this->expiresInDays,
            ],
            $this->setupUrl,
        );
    }

    private function firstName(): string
    {
        $name = trim((string) $this->instructor->user?->name);

        if ($name === '') {
            return 'there';
        }

        return explode(' ', $name)[0];
    }
}
