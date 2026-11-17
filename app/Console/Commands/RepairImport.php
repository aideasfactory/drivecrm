<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportRepairService;
use Illuminate\Console\Command;

class RepairImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:repair
        {--dry-run : Report what would change without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair what the Go Roadie import left behind: cancel future imported lessons that duplicate the same pupil\'s confirmed Drive lesson, flag other clashes provisional, remove open slots sitting under imported lessons, and merge duplicate progress categories/skills. No emails. Safe to re-run.';

    public function handle(ImportRepairService $importRepairService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $importRepairService->repair($dryRun);
        $will = $dryRun ? 'to remove' : 'removed';

        $this->info('1. Future imported lessons clashing with a Drive lesson');
        $this->table(['', 'Count'], [
            ['Clashing', $result['lessons']['counts']['clashing']],
            [$dryRun ? 'To cancel (same pupil, confirmed Drive lesson)' : 'Cancelled (same pupil, confirmed Drive lesson)', $result['lessons']['counts']['cancelled']],
            [$dryRun ? 'To flag provisional (office decides)' : 'Flagged provisional (office decides)', $result['lessons']['counts']['flagged']],
            ['Already provisional', $result['lessons']['counts']['already_flagged']],
            ['Skipped', $result['lessons']['counts']['skipped']],
        ]);

        if ($result['lessons']['lessons'] !== []) {
            $this->table(
                ['Lesson', 'Date', 'Time', 'Instructor', 'Imported pupil', 'Drive lesson(s)', 'Drive pupil(s)', 'Outcome'],
                array_map(fn (array $lesson): array => [
                    $lesson['lesson_id'],
                    $lesson['date'],
                    $lesson['time'],
                    $lesson['instructor_id'],
                    $lesson['student_id'],
                    $lesson['drive_lesson_ids'],
                    $lesson['drive_student_ids'],
                    $lesson['outcome'],
                ], $result['lessons']['lessons']),
            );
        }

        $this->info('2. Open slots under imported lessons');
        $this->table(['', 'Count'], [
            ['Open slots found', $result['slots']['slots']],
            [ucfirst($will), $result['slots']['removed']],
            ['  …with their travel block', $result['slots']['travel_blocks']],
            ['  …instructors', $result['slots']['instructors']],
            ['Kept: has a slot offer', $result['slots']['skipped_slot_offer']],
            ['Kept: no longer empty', $result['slots']['skipped_not_empty']],
        ]);

        $this->info('3. Duplicate progress categories/skills');
        $this->table(['', 'Count'], [
            ['Duplicate categories merged', $result['progress']['categories_merged']],
            ['Duplicate skills merged', $result['progress']['skills_merged']],
            ['Skills moved to the kept category', $result['progress']['skills_moved']],
            ['Scores moved', $result['progress']['scores_moved']],
            ['Scores dropped (pupil already scored on the kept skill)', $result['progress']['scores_dropped']],
            ['Instructors', $result['progress']['instructors']],
        ]);

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
