<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\YearEndArchive\BuildYearEndArchiveAction;
use App\Actions\YearEndArchive\SendArchiveReadyEmailAction;
use App\Models\YearEndArchive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildYearEndArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Heavy zip build — keep on the default queue but allow up to 30 minutes.
     */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly int $archiveId) {}

    public function handle(
        BuildYearEndArchiveAction $build,
        SendArchiveReadyEmailAction $sendEmail,
    ): void {
        $archive = YearEndArchive::find($this->archiveId);
        if ($archive === null) {
            return;
        }

        $archive = ($build)($archive);

        if ($archive->status === YearEndArchive::STATUS_READY) {
            ($sendEmail)($archive);
        }
    }
}
