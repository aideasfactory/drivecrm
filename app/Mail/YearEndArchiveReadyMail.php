<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\YearEndArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class YearEndArchiveReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public YearEndArchive $archive,
        public string $signedUrl,
        public Carbon $linkExpiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.$this->archive->taxYearLabel().' tax-year archive is ready',
        );
    }

    public function content(): Content
    {
        $counts = $this->archive->counts ?? [];
        $fileSizeMb = $this->archive->file_size_bytes
            ? number_format($this->archive->file_size_bytes / 1024 / 1024, 2)
            : null;

        return new Content(
            view: 'emails.year-end-archive-ready',
            with: [
                'firstName' => $this->archive->instructor?->user?->name,
                'taxYearLabel' => $this->archive->taxYearLabel(),
                'downloadUrl' => $this->signedUrl,
                'linkExpiresAt' => $this->linkExpiresAt,
                'fileSizeMb' => $fileSizeMb,
                'financeRows' => (int) ($counts['finances'] ?? 0),
                'mileageRows' => (int) ($counts['mileage_logs'] ?? 0),
                'receipts' => (int) ($counts['receipts'] ?? 0),
                'submissions' => (int) ($counts['submissions'] ?? 0),
                'retentionYears' => (int) config('hmrc.year_end_archive.retention_years', 6),
            ],
        );
    }
}
