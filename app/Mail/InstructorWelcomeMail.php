<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstructorWelcomeMail extends Mailable
{
    use Queueable;
    use RendersTemplatedMail;
    use SerializesModels;

    private ?RenderedEmailTemplate $renderedCache = null;

    public function __construct(
        public User $user,
        public string $setupUrl,
        public CarbonInterface $linkExpiresAt,
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
            EmailTemplateKey::InstructorWelcome,
            [
                'recipient_name' => $this->firstName(),
                'app_name' => config('app.name'),
                'email' => $this->user->email,
                'setup_url' => $this->setupUrl,
                'link_expires_at' => $this->linkExpiresAt->format('j F Y'),
                'login_url' => url('/login'),
            ],
            $this->setupUrl,
        );
    }

    private function firstName(): string
    {
        $name = trim((string) $this->user->name);

        if ($name === '') {
            return 'there';
        }

        return explode(' ', $name)[0];
    }
}
