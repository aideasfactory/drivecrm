<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Models\User;
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
        public int $expiresInMinutes,
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
                'expires_in_minutes' => $this->expiresInMinutes,
                'expires_in' => $this->formatExpiry(),
                'link_validity' => $this->linkValidityMessage(),
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

    /**
     * Human-readable idle window for the shared password-reset token.
     */
    private function formatExpiry(): string
    {
        $minutes = $this->expiresInMinutes;

        if ($minutes <= 0) {
            return 'until you set your password';
        }

        if ($minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days === 1 ? '24 hours' : $days.' days';
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hour' : $hours.' hours';
        }

        return $minutes === 1 ? '1 minute' : $minutes.' minutes';
    }

    private function linkValidityMessage(): string
    {
        if ($this->expiresInMinutes <= 0) {
            return 'This setup link remains valid until you set your password.';
        }

        return 'For your security, this setup link remains valid for '.$this->formatExpiry().'.';
    }
}
