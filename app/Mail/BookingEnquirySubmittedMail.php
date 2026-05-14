<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingEnquirySubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Enquiry $enquiry) {}

    public function envelope(): Envelope
    {
        $step1 = $this->enquiry->getStepData(1) ?? [];
        $inArea = (bool) ($this->enquiry->getStepData(2)['in_area'] ?? false);
        $name = trim(($step1['first_name'] ?? '').' '.($step1['last_name'] ?? ''));

        $prefix = $inArea ? '[In area]' : '[Out of area]';
        $subject = $prefix.' New booking enquiry'.($name !== '' ? ' from '.$name : '');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $step1 = $this->enquiry->getStepData(1) ?? [];
        $step2 = $this->enquiry->getStepData(2) ?? [];

        return new Content(
            view: 'emails.booking-enquiry-submitted',
            with: [
                'firstName' => $step1['first_name'] ?? null,
                'lastName' => $step1['last_name'] ?? null,
                'email' => $step1['email'] ?? null,
                'phone' => $step1['phone'] ?? null,
                'postcode' => $step1['postcode'] ?? null,
                'inArea' => (bool) ($step2['in_area'] ?? false),
                'instructorId' => $step2['instructor_id'] ?? null,
                'enquiryId' => $this->enquiry->id,
                'submittedAt' => $this->enquiry->updated_at?->format('j M Y, H:i'),
                'enquiriesUrl' => url('/enquiries'),
            ],
        );
    }
}
