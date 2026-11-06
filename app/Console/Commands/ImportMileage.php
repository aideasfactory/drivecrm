<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportMileage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:mileage
        {file=imports/mileage.csv : CSV with mileage_ref, instructor_ref, date, end_date, start_odometer, end_odometer, type, vehicle_reg}
        {--dry-run : Report what would be added without changing anything}
        {--csv= : Also write every trip and its outcome to this CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import instructors\' mileage trips, matched by instructor ref. No vehicles are created: trips have no vehicle (the registration goes in the notes) until the instructor\'s primary-vehicle setup tags them. Only clean rows; trips already logged are skipped. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $importService->importMileage($path, $dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $result['counts'];
        $headers = ['Mileage ref', 'Instructor ref', 'Instructor ID', 'Date', 'Start', 'End', 'Miles', 'Type', 'Notes', 'Outcome'];

        $skipped = array_filter($result['rows'], fn (array $row) => str_starts_with($row['outcome'], 'Skipped'));

        if ($skipped !== []) {
            $this->line('Skipped:');
            $this->table($headers, array_map(fn (array $row) => array_values($row), $skipped));
        }

        $this->table(['', 'Count'], [
            ['Rows in file', $counts['rows']],
            [$dryRun ? 'Trips to add' : 'Trips added', $counts['trips_added']],
            ['  …business', $counts['business']],
            ['  …personal', $counts['personal']],
            ['  …with "Vehicle: <reg>" in notes', $counts['with_registration_note']],
            ['  …for instructors', $counts['instructors']],
            ['Already imported', $counts['already_imported']],
            ['Skipped: not clean', $counts['skipped_not_clean']],
            ['Skipped: instructor not imported', $counts['skipped_no_instructor']],
            ['Skipped: instructor already has this trip', $counts['skipped_already_logged']],
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
