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
        {--by= : Email of the owner to record as turning alternative payments on (default: head office)}
        {--limit= : Only change this many records (the next batch needing a change) and list every change made}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off clean-up of imported rows: turn alternative payments on for every imported pupil and normalise UK phone numbers to 07… with no spaces — restores a lost leading 0, converts +44/44, strips spaces (pupils and instructors). Pupils already on Drive+ are not touched. Safe to re-run.';

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

        $limit = null;

        if ($this->option('limit') !== null) {
            if (! ctype_digit((string) $this->option('limit')) || (int) $this->option('limit') < 1) {
                $this->error('--limit must be a whole number of 1 or more.');

                return self::FAILURE;
            }

            $limit = (int) $this->option('limit');
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = $importService->fixImportedPupils($dryRun, $changedBy, $limit);

        $this->table(['', 'Count'], [
            ['Imported pupils', $counts['pupils']],
            ['Alternative payments turned on', $counts['alternative_payments_turned_on']],
            ['Pupil phone numbers fixed', $counts['pupil_phones_fixed']],
            ['Imported instructors', $counts['instructors']],
            ['Instructor phone numbers fixed', $counts['instructor_phones_fixed']],
        ]);

        if (($dryRun || $limit !== null) && $counts['examples'] !== []) {
            $this->line($limit !== null ? ($dryRun ? 'Changes that would be made:' : 'Changes made:') : 'Example changes:');
            $this->table(['Who', 'Field', 'Before', 'After'], $counts['examples']);
        }

        if ($limit !== null && $counts['examples'] === []) {
            $this->line('Nothing left to change.');
        }

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
