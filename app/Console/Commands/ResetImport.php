<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;

class ResetImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:reset
        {--force : Skip the confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unstick a Data Import whose worker died: marks queued/running imports as stopped and frees the one-import-at-a-time lock, so the zip can be uploaded again. Already imported rows are kept and skipped on the next run.';

    public function handle(ImportService $importService): int
    {
        $runs = $importService->getActiveRuns();

        if ($runs->isEmpty()) {
            $this->info('No import is queued or running.');
        } else {
            $this->table(['Run', 'Status', 'File', 'Progress', 'Last progress'], $runs->map(fn ($run) => [
                $run->id,
                $run->status,
                $run->file_name,
                ($run->totals['instructors_done'] ?? 0).' of '.($run->totals['instructors_to_import'] ?? '?').' instructors',
                $run->updated_at?->diffForHumans(),
            ])->all());
        }

        $this->warn('Only reset when the import has really stopped. If it is still running, a new upload would overlap it and could create duplicates.');

        if (! $this->option('force') && ! $this->confirm('Reset the import?')) {
            return self::FAILURE;
        }

        $count = $importService->resetActiveRuns();

        $this->info("Reset {$count} run(s) and freed the import lock. Upload the zip again to carry on.");

        return self::SUCCESS;
    }
}
