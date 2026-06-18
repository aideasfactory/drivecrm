<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PupilPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $newPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('app.name').' password has been reset',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pupil-password-reset',
            with: [
                'pupilName' => $this->firstName(),
                'email' => $this->user->email,
                'newPassword' => $this->newPassword,
                'loginUrl' => url('/login'),
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
