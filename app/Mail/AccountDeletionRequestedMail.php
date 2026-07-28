<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public AccountDeletionRequest $deletionRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' account is scheduled for deletion',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion-requested',
            with: [
                'firstName' => $this->firstName(),
                'deletionDate' => $this->deletionRequest->scheduled_for->format('j F Y'),
                'appName' => config('app.name'),
                'loginUrl' => url('/login'),
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
