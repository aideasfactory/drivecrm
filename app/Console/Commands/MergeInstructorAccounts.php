<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InstructorMergeService;
use Illuminate\Console\Command;
use RuntimeException;

class MergeInstructorAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instructor:merge
        {from : Email of the instructor account to empty and retire (e.g. the imported account)}
        {into : Email of the instructor account to keep (its login and Stripe Connect account are untouched)}
        {--dry-run : Report what would change without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off: move every pupil, lesson, order, package, diary day, finance, mileage log, location and import mapping from one instructor account onto another, then lock the old login and archive the old profile. Refuses if the old account has Stripe, payouts, HMRC data or pupil progress.';

    public function handle(InstructorMergeService $instructorMergeService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $instructorMergeService->merge(
                (string) $this->argument('from'),
                (string) $this->argument('into'),
                $dryRun,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $from = $result['from'];
        $into = $result['into'];
        $report = $result['report'];

        $this->line(sprintf(
            '%s instructor #%d (%s, user #%d) into instructor #%d (%s, user #%d, Stripe %s).',
            $dryRun ? 'Would merge' : 'Merged',
            $from->id,
            (string) $this->argument('from'),
            $from->user_id,
            $into->id,
            $into->user->email,
            $into->user_id,
            $into->stripe_account_id ?? 'not connected',
        ));

        $rows = [];
        foreach ($report['tables'] as $table => $count) {
            $rows[] = [$table, $count];
        }
        foreach ($report['user_columns'] as $column => $count) {
            $rows[] = [$column.' (user)', $count];
        }
        $rows[] = ['Diary days moved', $report['calendar_days_moved']];
        $rows[] = ['Diary days merged into existing days', $report['calendar_days_merged']];
        $rows[] = ['  …slots moved onto those days', $report['calendar_slots_moved_onto_existing_days']];
        $rows[] = ['  …empty availability slots + travel removed under them', $report['calendar_empty_slots_removed']];
        $rows[] = ['  …still overlapping another item (left as they are)', $report['calendar_slots_clashing']];
        $rows[] = ['Progress categories dropped (old set, unused)', $report['progress_categories_dropped']];
        $rows[] = ['Activity log entries', $report['activity_logs']];
        $rows[] = ['Contacts', $report['contacts']];
        $rows[] = ['Import mappings', $report['import_mappings']];

        $this->table([$dryRun ? 'Would move' : 'Moved', 'Rows'], $rows);

        $this->line(sprintf(
            'Old login %s email → %s, password randomised, tokens and sessions removed; old profile archived.',
            $dryRun ? 'would be locked:' : 'locked:',
            $report['retired_email'],
        ));

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
