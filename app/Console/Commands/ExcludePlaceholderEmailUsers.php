<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImportService;
use Illuminate\Console\Command;

class ExcludePlaceholderEmailUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:exclude-placeholder-emails
        {--dry-run : Count the users that would be flagged, without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flag users with a placeholder email (no-email+…, example@…-student.com) as excluded from emails, so the mail guard and welcome emails skip them. Changes nothing else; sends nothing. Safe to re-run.';

    public function handle(ImportService $importService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $importService->excludePlaceholderEmailUsers($dryRun);

        $this->table(['Email style', 'Users not yet flagged'], array_map(
            fn (string $style, int $count) => [$style, $count],
            array_keys($result['by_style']),
            $result['by_style'],
        ));

        $this->line(sprintf('%s: %d user(s).', $dryRun ? 'Would flag' : 'Flagged', $result['users']));
        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
