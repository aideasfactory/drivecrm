<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Enums\OwnerAccess;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminWelcomeMail extends Mailable
{
    use Queueable;
    use RendersTemplatedMail;
    use SerializesModels;

    private ?RenderedEmailTemplate $renderedCache = null;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
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
            EmailTemplateKey::AdminWelcome,
            [
                'recipient_name' => $this->firstName(),
                'app_name' => config('app.name'),
                'email' => $this->user->email,
                'temporary_password' => $this->temporaryPassword,
                'access_level' => ($this->user->owner_access ?? OwnerAccess::All)->label(),
                'login_url' => url('/login'),
            ],
            url('/login'),
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
