<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResendTestMail extends Mailable
{
    public function __construct(public string $sentAt) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Drive CRM — Resend test',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>This is a Resend transport test from Drive CRM.</p>'
                .'<p>Sent at '.$this->sentAt.'.</p>',
        );
    }
}
