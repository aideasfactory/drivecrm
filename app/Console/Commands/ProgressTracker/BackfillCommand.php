<?php

declare(strict_types=1);

namespace App\Console\Commands\ProgressTracker;

use App\Actions\ProgressTracker\SeedInstructorProgressTrackerAction;
use App\Models\Instructor;
use Illuminate\Console\Command;

class BackfillCommand extends Command
{
    protected $signature = 'progress-tracker:backfill';

    protected $description = 'Seed the default progress-tracker framework for every instructor that does not yet have one.';

    public function handle(SeedInstructorProgressTrackerAction $seed): int
    {
        $total = Instructor::count();
        $this->info("Backfilling progress tracker for {$total} instructor(s). Already-seeded records are skipped.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Instructor::query()->chunkById(100, function ($instructors) use ($seed, $bar): void {
            foreach ($instructors as $instructor) {
                $seed($instructor);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
