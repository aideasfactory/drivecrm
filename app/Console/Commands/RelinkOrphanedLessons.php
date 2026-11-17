<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DiaryRepairService;
use Illuminate\Console\Command;

class RelinkOrphanedLessons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diary:relink-orphaned-lessons
        {--dry-run : Report what would change without changing anything}
        {--lesson=* : Only these lesson ids}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give booked lessons that lost their diary slot (a move deleted the old day first) a slot again on the lesson\'s own date and time. Clashing lessons are reported and skipped. Reports payment state for every lesson. No emails, no payment changes. Safe to re-run.';

    public function handle(DiaryRepairService $diaryRepairService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $lessonIds = array_map('intval', (array) $this->option('lesson'));
        $result = $diaryRepairService->relinkOrphanedLessons($dryRun, $lessonIds);
        $counts = $result['counts'];

        $this->table(['', 'Count'], [
            ['Lessons with no diary slot', $counts['found']],
            [$dryRun ? 'To relink' : 'Relinked', $counts['relinked']],
            ['  …open slots underneath '.($dryRun ? 'to remove' : 'removed'), $counts['open_slots_removed']],
            ['Skipped: time now taken (clash)', $counts['clashing']],
            ['Skipped: order cancelled/expired', $counts['order_closed']],
            ['With a payment issue', $counts['payment_issues']],
            ['With another lesson for the pupil within 6 days', $counts['possible_duplicates']],
        ]);

        if ($result['lessons'] !== []) {
            $this->table(
                ['Lesson', 'Instructor', 'Pupil', 'Date', 'Time', 'Payment', 'Outcome'],
                array_map(fn (array $lesson): array => [
                    $lesson['lesson_id'],
                    $lesson['instructor'],
                    $lesson['pupil'],
                    $lesson['date'],
                    $lesson['time'],
                    $lesson['payment'],
                    $lesson['outcome'],
                ], $result['lessons']),
            );
        }

        $this->printFlagged('Clashes — the lesson\'s time is now taken (skipped, sort out by hand)', $result['lessons'], 'clashes');
        $this->printFlagged('Payment issues', $result['lessons'], 'payment_issues');
        $this->printFlagged('Pupil has another lesson within 6 days (possible rebook — check before or after relinking)', $result['lessons'], 'possible_duplicates');

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lessons
     */
    protected function printFlagged(string $title, array $lessons, string $key): void
    {
        $flagged = array_values(array_filter($lessons, fn (array $lesson): bool => $lesson[$key] !== ''));

        $this->newLine();
        $this->info($title.': '.count($flagged));

        if ($flagged === []) {
            return;
        }

        $this->table(
            ['Lesson', 'Instructor', 'Pupil', 'Date', 'Time', 'Detail'],
            array_map(fn (array $lesson): array => [
                $lesson['lesson_id'],
                $lesson['instructor'],
                $lesson['pupil'],
                $lesson['date'],
                $lesson['time'],
                $lesson[$key],
            ], $flagged),
        );
    }
}
