<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportStudentDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:student-details
        {file=imports/Students_data.csv : CSV with ID, Date of Birth, Theory Test Passed, Practical Test Passed}
        {--dry-run : Report what would change without changing anything}
        {--csv= : Also write every change (and skipped date of birth) to this CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill imported pupils\' date of birth (only when empty) and tick "Sit theory test" / "Sit practical test" where the file says Yes. ID 119827 = pupil STU-119827. Nothing else changes. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        $dryRun = (bool) $this->option('dry-run');

        ini_set('memory_limit', '1024M');

        try {
            $result = $importService->importStudentDetails($path, $dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $result['counts'];
        $headers = ['ID', 'Pupil ID', 'Pupil', 'Field', 'Value', 'Outcome'];

        $skipped = array_filter($result['rows'], fn (array $row) => str_starts_with($row['outcome'], 'Skipped'));

        if ($skipped !== []) {
            $this->line('Skipped:');
            $this->table($headers, array_map(fn (array $row) => array_values($row), $skipped));
        }

        $this->table(['', 'Count'], [
            ['Rows in file', $counts['rows']],
            ['Pupils matched (ID → STU-ID)', $counts['pupils_matched']],
            ['Pupils skipped: not imported', $counts['pupils_not_imported']],
            ['Pupils skipped: deleted', $counts['pupils_deleted']],
            [$dryRun ? 'Date of birth to set' : 'Date of birth set', $counts['dob_set']],
            ['Date of birth already set (left alone)', $counts['dob_already_set']],
            ['Date of birth skipped: not a valid past date', $counts['dob_invalid']],
            [$dryRun ? '"Sit theory test" to tick' : '"Sit theory test" ticked', $counts['theory_ticked']],
            ['"Sit theory test" already ticked', $counts['theory_already_ticked']],
            [$dryRun ? '"Sit practical test" to tick' : '"Sit practical test" ticked', $counts['practical_ticked']],
            ['"Sit practical test" already ticked', $counts['practical_already_ticked']],
        ]);

        if ($this->option('csv')) {
            $this->writeCsv((string) $this->option('csv'), $headers, $result['rows']);
        }

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
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

        $this->info("Full list written to {$path}.");
    }
}
