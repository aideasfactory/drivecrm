<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' account deletion request has been cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion-cancelled',
            with: [
                'firstName' => $this->firstName(),
                'appName' => config('app.name'),
            ],
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
