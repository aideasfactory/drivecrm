<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportLessons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:lessons
        {file=imports/lesson_progress.csv : CSV with diary_ref, instructor_ref, student_ref, date, start_time, end_time, status (other columns are ignored)}
        {--dry-run : Report what would change without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add lessons from a diary-shaped CSV (e.g. lesson_progress.csv) that the diary import left out, for imported pupils linked to the row\'s imported instructor, on each pupil\'s imported order. Past lessons go in completed, future ones booked; already-imported lessons are never changed by their row. Then marks every past imported lesson still pending as completed (no emails, payments or payouts). Safe to re-run.';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['import:lesson'];

    public function handle(ImportService $importService): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) && is_file(base_path($path))) {
            $path = base_path($path);
        }

        ini_set('memory_limit', '1024M');

        try {
            return $this->option('dry-run')
                ? $this->dryRun($importService, $path)
                : $this->import($importService, $path);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function dryRun(ImportService $importService, string $path): int
    {
        $this->line("Reading {$path}…");

        $result = $importService->checkLessonFile($path);
        $counts = $result['counts'];

        $this->table(['', 'Count'], [
            ['Rows in file', $counts['rows']],
            ['Already imported', $counts['already_imported']],
            ['New lessons: completed (dated before today)', $counts['new_lessons_completed']],
            ['New lessons: booked (today onwards)', $counts['new_lessons_booked']],
            ['  …for pupils', $counts['pupils']],
            ['  …with instructors', $counts['instructors']],
            ['Past imported lessons still pending, to mark completed', $counts['lessons_to_complete']],
            ['Skipped: pupil already has a lesson then', $counts['lessons_already_in_diary']],
            ['Skipped: bad date/time (blank, before 2000, end not after start)', $counts['skipped_bad_date']],
            ['Skipped: cancelled', $counts['skipped_cancelled']],
            ['Skipped: instructor not imported', $counts['skipped_no_instructor']],
            ['Skipped: pupil not imported', $counts['skipped_no_pupil']],
            ['Skipped: pupil not linked to that instructor', $counts['skipped_pupil_not_linked']],
        ]);

        $this->listMissingRefs('Instructor refs not found', $result['missing_instructor_refs']);
        $this->listMissingRefs('Pupil refs not found', $result['missing_student_refs']);

        $this->line('Dry run — nothing was changed.');

        return self::SUCCESS;
    }

    protected function import(ImportService $importService, string $path): int
    {
        $this->line("Importing {$path}…");

        $result = $importService->importLessonFile($path, fn (string $line) => $this->line($line));
        $totals = $result['totals'];

        $this->table(['', 'Count'], [
            ['Rows in file', $totals['rows']],
            ['Already imported', $totals['already_imported']],
            ['Lessons added', $totals['lessons']],
            ['Past imported lessons marked completed', $totals['lessons_marked_completed']],
            ['Skipped: pupil already has a lesson then', $totals['lessons_already_in_diary']],
            ['Skipped: bad date/time', $totals['skipped_bad_date']],
            ['Skipped: cancelled', $totals['skipped_cancelled']],
            ['Skipped: instructor not imported', $totals['skipped_no_instructor']],
            ['Skipped: pupil not imported', $totals['skipped_no_pupil']],
            ['Skipped: pupil not linked to that instructor', $totals['skipped_pupil_not_linked']],
        ]);

        $this->info("Done. Import run #{$result['run']->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $refs  ref => rows, most rows first
     */
    protected function listMissingRefs(string $title, array $refs): void
    {
        if ($refs === []) {
            return;
        }

        $this->line(sprintf('%s (%d, top 10 by rows):', $title, count($refs)));
        $this->table(['Ref', 'Rows'], array_map(
            fn (string|int $ref, int $rows) => [$ref === '' ? '(blank)' : $ref, $rows],
            array_keys(array_slice($refs, 0, 10, true)),
            array_slice($refs, 0, 10, true),
        ));
    }
}
