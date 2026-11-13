<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\StagedImportService;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * One slice of an import stage: a handful of instructors, each imported in
 * its own transaction. Part of the stage's batch; the stages run one after
 * another (StagedImportService::startImport). Safe to retry: anything
 * already imported is skipped.
 */
class ImportStageJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 900;

    /**
     * Retried by time, not by attempt count: a students job waiting for the
     * stage lock is released, and each release counts as an attempt. Real
     * errors still fail the job after 3, and a timeout fails it at once.
     */
    public int $maxExceptions = 3;

    public bool $failOnTimeout = true;

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * @param  array<int, string>  $instructorRefs
     */
    public function __construct(
        public ImportRun $run,
        public string $stage,
        public array $instructorRefs,
    ) {}

    /**
     * Pupils are created one job at a time, so two jobs can never create
     * accounts for the same email at once.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if ($this->stage !== 'students') {
            return [];
        }

        return [
            (new WithoutOverlapping("import-run-{$this->run->id}-students"))
                ->releaseAfter(5)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(StagedImportService $stagedImportService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $totals = [];

        foreach ($this->instructorRefs as $instructorRef) {
            foreach ($stagedImportService->importStage($this->run, $this->stage, $instructorRef) as $key => $count) {
                $totals[$key] = ($totals[$key] ?? 0) + $count;
            }
        }

        $stagedImportService->recordStageProgress($this->run, $this->stage, $totals);
    }
}
