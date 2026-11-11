<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PackageRestoreService;
use Illuminate\Console\Command;

class RestorePackageSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'packages:restore-snapshot
        {source : URL or file path of the file from packages:export-snapshot}
        {--key= : The key printed by packages:export-snapshot}
        {--dry-run : Do the whole restore inside a transaction, report, then roll it back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Put back a deleted package and everything that was deleted with it, with the original ids. Only adds missing rows; refuses if any id or diary slot is now used by something else. Safe to re-run.';

    public function handle(PackageRestoreService $packageRestoreService): int
    {
        $key = (string) $this->option('key');

        if ($key === '') {
            $this->error('--key is required.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = $packageRestoreService->restoreFromSource((string) $this->argument('source'), $key, $dryRun);
        $report = $result['report'];

        $this->line("Package {$result['package_id']} snapshot from `{$result['source_database']}`, taken {$result['exported_at']}.");

        if ($report['problems'] !== []) {
            $this->error('Nothing was changed. Fix these first:');

            foreach ($report['problems'] as $problem) {
                $this->line(" - {$problem}");
            }

            return self::FAILURE;
        }

        $this->table(
            ['Table', 'In snapshot', 'Already here', $dryRun ? 'Would insert' : 'Inserted'],
            collect($report['tables'])
                ->map(fn (array $counts, string $table): array => [$table, $counts['in_snapshot'], $counts['already_present'], $counts['inserted']])
                ->values()
                ->all(),
        );

        $this->line(sprintf(
            '%s %d diary slot(s) to their snapshot values and %d test pass guarantee link(s).',
            $dryRun ? 'Would set back' : 'Set back',
            $report['slots_reset'],
            $report['guarantee_links_restored'],
        ));

        $this->line($report['committed'] ? 'Done. Committed.' : 'Dry run — everything was rolled back.');

        return self::SUCCESS;
    }
}
