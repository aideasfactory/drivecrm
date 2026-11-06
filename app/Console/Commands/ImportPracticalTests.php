<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportPracticalTests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:practical-tests
        {file=new-diary.csv : The diary CSV that was imported (diary_ref, …, notes)}
        {--dry-run : Report what would change without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Turn imported lessons whose diary notes start "Driving test" into practical tests: the calendar item becomes a practical test for the pupil, the lesson is removed, and the pupil\'s "Book practical test" checklist item is ticked with their latest test date. Run after import:lessons. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        $dryRun = (bool) $this->option('dry-run');

        ini_set('memory_limit', '1024M');

        $this->line("Reading {$path}…");

        try {
            $counts = $importService->importPracticalTests($path, $dryRun, fn (string $line) => $this->line($line));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['', 'Count'], [
            ['Rows in file', $counts['rows']],
            ['Rows whose notes start "Driving test"', $counts['test_rows']],
            [$dryRun ? 'Tests to convert: past' : 'Tests converted: past', $counts['tests_past']],
            [$dryRun ? 'Tests to convert: future' : 'Tests converted: future', $counts['tests_future']],
            ['  …for pupils', $counts['pupils']],
            ['"Book practical test" checklist ticked/dated', $counts['checklists_updated']],
            ['Already converted', $counts['already_converted']],
            ['Skipped: lesson not imported', $counts['skipped_not_imported']],
            ['Skipped: lesson cancelled', $counts['skipped_cancelled']],
            ['Skipped: lesson banked', $counts['skipped_banked']],
            ['Skipped: no calendar item', $counts['skipped_no_calendar_item']],
            ['Skipped: has a payment, payout or reflective log', $counts['skipped_has_payment_or_log']],
            ['Skipped: pupil already has a test that day', $counts['skipped_pupil_has_test_that_day']],
        ]);

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
