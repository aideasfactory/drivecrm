<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InstructorFinanceExportMail extends Mailable
{
    use Queueable;
    use RendersTemplatedMail;

    private ?RenderedEmailTemplate $renderedCache = null;

    /**
     * @param  array{payments: int, expenses: int, mileage: int}  $counts
     */
    public function __construct(
        public string $recipientName,
        public string $period,
        public string $filename,
        public string $csv,
        public array $counts,
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

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->csv, $this->filename)->withMime('text/csv'),
        ];
    }

    private function rendered(): RenderedEmailTemplate
    {
        return $this->renderedCache ??= $this->renderedTemplate(
            EmailTemplateKey::InstructorFinanceExport,
            [
                'recipient_name' => $this->recipientName,
                'period' => $this->period,
                'payment_rows' => number_format($this->counts['payments']),
                'expense_rows' => number_format($this->counts['expenses']),
                'mileage_rows' => number_format($this->counts['mileage']),
            ],
        );
    }
}
