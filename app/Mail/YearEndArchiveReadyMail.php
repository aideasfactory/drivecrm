<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\EmailTemplateKey;
use App\Models\YearEndArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class YearEndArchiveReadyMail extends Mailable
{
    use Queueable;
    use RendersTemplatedMail;
    use SerializesModels;

    private ?RenderedEmailTemplate $renderedCache = null;

    public function __construct(
        public YearEndArchive $archive,
        public string $signedUrl,
        public Carbon $linkExpiresAt,
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
        $counts = $this->archive->counts ?? [];
        $fileSizeMb = $this->archive->file_size_bytes
            ? number_format($this->archive->file_size_bytes / 1024 / 1024, 2)
            : null;

        return $this->renderedCache ??= $this->renderedTemplate(
            EmailTemplateKey::InstructorYearEndArchiveReady,
            [
                'recipient_name' => $this->archive->instructor?->user?->name ?: 'there',
                'tax_year_label' => $this->archive->taxYearLabel(),
                'file_size' => $fileSizeMb ? ' ('.$fileSizeMb.' MB)' : '',
                'finance_rows' => number_format((int) ($counts['finances'] ?? 0)),
                'mileage_rows' => number_format((int) ($counts['mileage_logs'] ?? 0)),
                'receipts' => number_format((int) ($counts['receipts'] ?? 0)),
                'submissions' => number_format((int) ($counts['submissions'] ?? 0)),
                'link_expires_at' => $this->linkExpiresAt->format('j M Y, H:i').' UK time',
                'retention_years' => (int) config('hmrc.year_end_archive.retention_years', 6),
            ],
            $this->signedUrl,
        );
    }
}
