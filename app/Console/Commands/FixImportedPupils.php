<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ImportService;
use Illuminate\Console\Command;

class FixImportedPupils extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:fix-pupils
        {--dry-run : Count what would change without changing anything}
        {--by= : Email of the owner to record as turning alternative payments on (default: head office)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off clean-up of imported rows: turn alternative payments on for every imported pupil and restore the leading 0 on UK phone numbers (pupils and instructors). Pupils already on Drive+ are not touched. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $changedBy = null;

        if ($this->option('by')) {
            $changedBy = User::query()->where('email', (string) $this->option('by'))->first();

            if (! $changedBy) {
                $this->error("No user with email {$this->option('by')}.");

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = $importService->fixImportedPupils($dryRun, $changedBy);

        $this->table(['', 'Count'], [
            ['Imported pupils', $counts['pupils']],
            ['Alternative payments turned on', $counts['alternative_payments_turned_on']],
            ['Pupil phone numbers fixed', $counts['pupil_phones_fixed']],
            ['Imported instructors', $counts['instructors']],
            ['Instructor phone numbers fixed', $counts['instructor_phones_fixed']],
        ]);

        if ($dryRun && $counts['examples'] !== []) {
            $this->line('Example phone changes:');
            $this->table(['Who', 'Field', 'Before', 'After'], $counts['examples']);
        }

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
