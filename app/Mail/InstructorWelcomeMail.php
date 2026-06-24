<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstructorWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $setupUrl,
        public int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name').' — set up your instructor account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.instructor-welcome',
            with: [
                'instructorName' => $this->firstName(),
                'email' => $this->user->email,
                'setupUrl' => $this->setupUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
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
