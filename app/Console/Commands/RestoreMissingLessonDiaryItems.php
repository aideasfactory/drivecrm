<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Lesson\RestoreMissingLessonDiaryItemsAction;
use Illuminate\Console\Command;

class RestoreMissingLessonDiaryItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lessons:restore-diary-items {--dry-run : List the lessons that would get a diary item back without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give a booked diary item back to every open, dated lesson that lost it (moved off a day where it was the only item), so instructors can move, rebook later or cancel it again. No emails, payments or Stripe. Safe to re-run.';

    /**
     * Execute the console command.
     */
    public function handle(RestoreMissingLessonDiaryItemsAction $restoreMissingLessonDiaryItems): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = $restoreMissingLessonDiaryItems($dryRun);

        if ($rows === []) {
            $this->info('No open lessons are missing their diary item.');

            return self::SUCCESS;
        }

        $this->table(
            ['Lesson', 'Instructor', 'Pupil', 'Date', 'Time', 'Payment mode', 'Diary item', 'Outcome'],
            array_map(fn (array $row): array => [
                $row['lesson_id'],
                $row['instructor_id'],
                $row['student_id'],
                $row['date'],
                $row['time'],
                $row['payment_mode'],
                $row['calendar_item_id'],
                $row['outcome'],
            ], $rows),
        );

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
