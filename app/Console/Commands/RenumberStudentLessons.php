<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenumberStudentLessons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lessons:renumber {--dry-run : Report how many lessons would be renumbered without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off backfill: renumber every student\'s open lessons chronologically by lesson date/time (completed lessons keep their numbers)';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $studentIds = Order::query()->distinct()->pluck('student_id')->filter();

        $studentsChanged = 0;
        $lessonsChanged = 0;

        foreach ($studentIds as $studentId) {
            if ($dryRun) {
                DB::beginTransaction();
            }

            $changed = $recalculateStudentLessonNumbers((int) $studentId);

            if ($dryRun) {
                DB::rollBack();
            }

            if ($changed > 0) {
                $studentsChanged++;
                $lessonsChanged += $changed;
                $this->line("Student #{$studentId}: {$changed} lesson(s) ".($dryRun ? 'would be renumbered' : 'renumbered'));
            }
        }

        $verb = $dryRun ? 'Would renumber' : 'Renumbered';
        $this->info("{$verb} {$lessonsChanged} lesson(s) across {$studentsChanged} of {$studentIds->count()} student(s).");

        if (! $dryRun) {
            Log::info('Student lesson number backfill completed', [
                'students_checked' => $studentIds->count(),
                'students_changed' => $studentsChanged,
                'lessons_renumbered' => $lessonsChanged,
            ]);
        }

        return Command::SUCCESS;
    }
}
