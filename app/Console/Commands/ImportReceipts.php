<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportReceipts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:receipts
        {folder=imports/receipts : Folder of receipt files named after their finance ref (e.g. F-169242.png)}
        {--dry-run : List which finance record each file would attach to, without uploading anything}
        {--csv= : Also write the list to this CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach receipt files to finance records already imported, matched by finance ref. Only records with no receipt get one (never replaced); stored like an instructor\'s own upload. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $folder = (string) $this->argument('folder');

        if (! is_dir($folder) && is_dir(base_path($folder))) {
            $folder = base_path($folder);
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $importService->importReceipts($folder, $dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $headers = ['File', 'Ref', 'Finance ID', 'Instructor', 'Date', 'Type', 'Category', 'Amount', 'Description', 'Outcome'];

        $this->table($headers, array_map(fn (array $row) => array_values($row), $result['rows']));

        $this->table(['', 'Count'], [
            [$dryRun ? 'Would attach' : 'Attached', $result['counts']['attach']],
            ['Skipped: already has a receipt', $result['counts']['already_has_receipt']],
            ['Skipped: ref not imported', $result['counts']['ref_not_imported']],
            ['Skipped: finance row deleted', $result['counts']['finance_row_missing']],
            ['Skipped: more than one file for this ref', $result['counts']['duplicate_file']],
            ['Skipped: not a pdf, jpg or png', $result['counts']['unsupported_type']],
            ['Failed', $result['counts']['failed']],
        ]);

        if ($this->option('csv')) {
            $this->writeCsv((string) $this->option('csv'), $headers, $result['rows']);
        }

        $this->line($dryRun ? 'Dry run — nothing was uploaded or changed.' : 'Done. Photos are shrunk and thumbnailed by the queue worker.');

        return $result['counts']['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            $this->error("Could not write {$path}.");

            return;
        }

        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), ',', '"', '');
        }

        fclose($handle);

        $this->info("List written to {$path}.");
    }
}
