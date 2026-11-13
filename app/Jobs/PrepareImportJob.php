<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\StagedImportService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * First step of a Data Import upload, in the background: read the zip into
 * the staging table, check it against its manifest, and check every row.
 * A check-only run (`dry_run`) stops there with its report and writes
 * nothing to Drive's data. An import with no problems then queues its
 * stages (StagedImportService::startImport).
 *
 * Shares the one-at-a-time lock with ImportBundleJob, so a check never runs
 * alongside an import.
 */
class PrepareImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    /** Waiting behind another import counts as an attempt, so retry by time; a real error fails at once. */
    public int $maxExceptions = 1;

    public function __construct(
        public ImportRun $run,
        public string $storedZipPath,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping(ImportBundleJob::OVERLAP_KEY))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(StagedImportService $stagedImportService): void
    {
        $stagedImportService->prepareImportRun($this->run, $this->storedZipPath);
    }

    public function failed(?Throwable $exception): void
    {
        app(StagedImportService::class)->failPreparedRun($this->run, $this->storedZipPath, $exception);
    }
}
