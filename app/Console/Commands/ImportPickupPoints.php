<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ParseImportedLocationsAction;
use App\Services\ImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportPickupPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:pickup-points
        {file=imports/student_locations.csv : CSV with student_ref and locations (joined by "|")}
        {--dry-run : Report what would be added without changing anything}
        {--csv= : Also write the full list (every location and its outcome) to this CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give imported pupils their pick-up points from the locations CSV, matched by student ref. Pupils who already have a pick-up point are skipped; only cleanly formed locations (full postcode at the end) are added; the first is the default ("Home" when unlabelled, others "Pick-up point"). No geocoding. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        $dryRun = (bool) $this->option('dry-run');

        ini_set('memory_limit', '1024M');

        try {
            $result = $importService->importPickupPoints($path, $dryRun);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $result['counts'];
        $headers = ['Student ref', 'Pupil ID', 'Pupil', 'Label', 'Address', 'Postcode', 'Default', 'Outcome'];

        $skipped = array_filter(
            $result['rows'],
            fn (array $row) => str_starts_with($row['outcome'], 'Skipped') && ! str_ends_with($row['outcome'], ParseImportedLocationsAction::REPEAT_REASON),
        );

        if ($skipped !== []) {
            $this->line('Skipped:');
            $this->table($headers, array_map(fn (array $row) => array_values($row), $skipped));
        }

        $this->table(['', 'Count'], [
            [$dryRun ? 'Pupils who would get pick-up points' : 'Pupils given pick-up points', $counts['pupils_given_points']],
            [$dryRun ? 'Pick-up points to add' : 'Pick-up points added', $counts['points_added']],
            ['  …of which default', $counts['defaults_set']],
            ['Locations skipped (not clean)', $counts['locations_skipped']],
            ['Locations skipped: repeat of an earlier one (in --csv only)', $counts['locations_repeated']],
            ['Pupils skipped: not imported', $counts['pupils_not_imported']],
            ['Pupils skipped: already have a pick-up point', $counts['pupils_already_have_points']],
            ['Refs skipped: two refs for the same pupil', $counts['pupils_with_two_refs']],
            ['Pupils with no clean location', $counts['pupils_no_clean_locations']],
            ['Pupils with no locations in the file', $counts['pupils_no_locations']],
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
