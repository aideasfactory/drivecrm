<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Lesson\FindDuplicateLessonsAction;
use App\Services\DuplicateLessonService;
use Illuminate\Console\Command;

class FindDuplicateLessons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lessons:find-duplicates
        {--output= : CSV path (default storage/app/duplicate-lessons/duplicate-lessons-{timestamp}.csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read-only: scan every instructor\'s diary for duplicated lessons (same GoRoadie booking ID, same instructor + pupil + date + start time, or a GoRoadie original re-imported at the time a Drive Plus lesson was moved from) and write a CSV marking each lesson KEEP or REMOVE, with reasons and a manual-review flag. Changes no data — send the CSV for review; any clean-up is a separate one-off script.';

    public function handle(DuplicateLessonService $duplicateLessonService): int
    {
        ini_set('memory_limit', '1024M');

        $this->line('Scanning all instructors for duplicate lessons…');

        $result = $duplicateLessonService->findDuplicates($this->option('output') ?: null);
        $counts = $result['counts'];

        $this->table(['', 'Count'], [
            ['Duplicate groups', $counts['groups']],
            ['  …same GoRoadie booking ID', $counts['groups_'.FindDuplicateLessonsAction::TYPE_GOROADIE_ID]],
            ['  …exact duplicate (instructor, pupil, date, start time)', $counts['groups_'.FindDuplicateLessonsAction::TYPE_EXACT]],
            ['  …GoRoadie original at the time a Drive Plus lesson was moved from', $counts['groups_'.FindDuplicateLessonsAction::TYPE_MOVED]],
            ['Instructors affected', $counts['instructors']],
            ['Lessons to keep', $counts['lessons_to_keep']],
            ['Lessons to remove', $counts['lessons_to_remove']],
            ['Groups needing manual review', $counts['groups_needing_review']],
            ['  …lessons marked REMOVE in those groups', $counts['lessons_to_remove_after_review']],
        ]);

        $flagged = array_filter($result['groups'], fn (array $group): bool => $group['needs_review']);

        if ($flagged !== []) {
            $this->table(['Group', 'Instructor', 'Lessons', 'Why it needs review'], array_map(fn (array $group): array => [
                $group['group_id'],
                $group['rows'][0]['instructor_id'].' '.$group['rows'][0]['instructor_name'],
                implode(', ', array_column($group['rows'], 'lesson_id')),
                implode('; ', $group['review_reasons']),
            ], array_values($flagged)));
        }

        $this->info("CSV written to {$result['path']}");
        $this->line('Nothing was changed.');

        return self::SUCCESS;
    }
}
