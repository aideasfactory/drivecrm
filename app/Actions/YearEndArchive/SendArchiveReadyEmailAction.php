<?php

declare(strict_types=1);

namespace App\Actions\YearEndArchive;

use App\Mail\YearEndArchiveReadyMail;
use App\Models\YearEndArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

class SendArchiveReadyEmailAction
{
    /**
     * Send the "your archive is ready" email as a Blade-rendered Mailable
     * (Mandrill is just the transport, configured via MAIL_MAILER). Includes a
     * signed download URL with the configured TTL. Logs and swallows transport
     * failures — the archive itself is already built and available in the UI.
     */
    public function __invoke(YearEndArchive $archive): void
    {
        $instructor = $archive->instructor;
        $user = $instructor?->user;

        if (! $user || ! is_string($user->email) || $user->email === '') {
            Log::warning('Year-end archive: cannot notify, instructor has no email', [
                'archive_id' => $archive->id,
            ]);

            return;
        }

        $ttlHours = (int) config('hmrc.year_end_archive.download_url_ttl_hours', 24);
        $linkExpiresAt = Carbon::now()->addHours($ttlHours);

        $signedUrl = URL::temporarySignedRoute(
            'hmrc.archive.download',
            $linkExpiresAt,
            ['archive' => $archive->id],
        );

        try {
            Mail::to($user->email, $user->name)
                ->send(new YearEndArchiveReadyMail($archive, $signedUrl, $linkExpiresAt));
        } catch (Throwable $e) {
            Log::warning('Year-end archive: email send failed', [
                'archive_id' => $archive->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
