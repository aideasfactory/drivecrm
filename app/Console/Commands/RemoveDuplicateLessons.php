<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DuplicateLessonService;
use Illuminate\Console\Command;
use RuntimeException;

class RemoveDuplicateLessons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lessons:remove-duplicates
        {--csv= : The reviewed CSV written by lessons:find-duplicates}
        {--group=* : Only these group ids (e.g. DUP-123)}
        {--confirm : Make the changes (without it this is a dry run)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the lessons marked REMOVE in a reviewed lessons:find-duplicates CSV. Dry run unless --confirm. Skips groups flagged for manual review and any lesson that changed since the scan, is not on an imported (£0) order, or has a lesson payment or payout. Moves GoRoadie finance rows, reflective logs, resources and import mappings to the kept lesson (amounts never change), then cancels the duplicate quietly (kept for history, no emails) and removes its diary slot. One transaction per group; every change is written to storage/logs/lessons-remove-duplicates-*.jsonl.';

    public function handle(DuplicateLessonService $duplicateLessonService): int
    {
        $csv = (string) $this->option('csv');

        if ($csv === '') {
            $this->error('Pass the reviewed CSV: --csv=storage/app/duplicate-lessons/….csv');

            return self::FAILURE;
        }

        if (! is_file($csv) && is_file(base_path($csv))) {
            $csv = base_path($csv);
        }

        $confirm = (bool) $this->option('confirm');

        if ($confirm && ! $this->confirm("Remove the duplicate lessons marked REMOVE in {$csv}? Take a database backup first.")) {
            return self::FAILURE;
        }

        try {
            $result = $duplicateLessonService->removeDuplicates($csv, $confirm, array_values((array) $this->option('group')));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = $result['counts'];

        if ($result['lessons'] !== []) {
            $this->table(['Group', 'Lesson', 'Keep', 'Outcome'], array_map(fn (array $lesson): array => [
                $lesson['group_id'],
                $lesson['lesson_id'] ?? '—',
                $lesson['keep_lesson_id'] ?? '—',
                $lesson['outcome'],
            ], $result['lessons']));
        }

        $this->table(['', 'Count'], [
            ['Groups in CSV (after --group filter)', $counts['groups']],
            [$confirm ? 'Groups done' : 'Groups that would be done', $counts['groups_done']],
            ['Groups skipped', $counts['groups_skipped']],
            [$confirm ? 'Lessons removed' : 'Lessons that would be removed', $counts['lessons_removed']],
            ['Lessons skipped', $counts['lessons_skipped']],
        ]);

        if ($confirm) {
            $this->info("Audit log: {$result['audit_path']}");
        } else {
            $this->line('Dry run — nothing was changed. Run again with --confirm to make these changes.');
        }

        return self::SUCCESS;
    }
}
