<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\StagedImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Last step of an import, after every stage: quietly complete past imported
 * lessons still pending, clear the staged rows and mark the run completed.
 */
class FinishImportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 2;

    public function __construct(
        public ImportRun $run,
    ) {}

    public function handle(StagedImportService $stagedImportService): void
    {
        $stagedImportService->finishImport($this->run);
    }
}
