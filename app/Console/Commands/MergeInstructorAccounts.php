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
        {from : Email of the instructor account to empty and retire}
        {into : Email of the instructor account to keep (its data, sessions and Stripe Connect account are untouched)}
        {--take-email : After retiring the "from" login, give its email to the kept login}
        {--leave-stripe : Allow retiring an account with a Stripe Connect account (it stays on the archived profile with its payout history)}
        {--dry-run : Report what would change without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off: move every pupil, lesson, order, package, diary day, finance, progress score, location and import mapping from one instructor account onto another, then lock the old login and archive the old profile. Orders with a paid-out lesson and their payouts stay on the old account.';

    public function handle(InstructorMergeService $instructorMergeService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fromEmail = (string) $this->argument('from');

        try {
            $result = $instructorMergeService->merge(
                $fromEmail,
                (string) $this->argument('into'),
                (bool) $this->option('take-email'),
                (bool) $this->option('leave-stripe'),
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
            '%s instructor #%d (%s, user #%d, Stripe %s) into instructor #%d (user #%d, Stripe %s).',
            $dryRun ? 'Would merge' : 'Merged',
            $from->id,
            $fromEmail,
            $from->user_id,
            $from->stripe_account_id ?? 'not connected',
            $into->id,
            $into->user_id,
            $into->stripe_account_id ?? 'not connected',
        ));

        $rows = [];
        foreach ($report['tables'] as $table => $count) {
            $rows[] = [$table, $count];
        }
        $rows[] = ['Diary items moved (lessons, blocks, travel)', $report['calendar_items_moved']];
        $rows[] = ['  …onto new days', $report['calendar_days_moved']];
        $rows[] = ['  …merged into days the kept account already has', $report['calendar_days_merged']];
        $rows[] = ['  …still overlapping a kept item (left as they are)', $report['calendar_slots_clashing']];
        $rows[] = ['Empty availability slots + travel removed', $report['calendar_empty_slots_removed']];
        $rows[] = ['Old diary days left empty and dropped', $report['calendar_days_dropped']];
        $rows[] = ['Progress scores moved onto matching tracker items', $report['progress_scores_moved']];
        $rows[] = ['Progress categories dropped (old tracker)', $report['progress_categories_dropped']];
        $rows[] = ['Activity log entries', $report['activity_logs']];
        $rows[] = ['Contacts', $report['contacts']];
        $rows[] = ['Import mappings', $report['import_mappings']];

        $this->table([$dryRun ? 'Would move' : 'Moved', 'Rows'], $rows);

        $this->table(['Staying on the retired account (payout history)', 'Rows'], [
            ['Payouts', $report['staying_payouts']],
            ['Orders with a paid-out lesson', $report['staying_orders']],
            ['  …and their lessons', $report['staying_lessons']],
        ]);

        $this->line(sprintf(
            'Old login %s email → %s, password randomised, tokens and sessions removed; old profile archived.',
            $dryRun ? 'would be locked:' : 'locked:',
            $report['retired_email'],
        ));
        $this->line(sprintf(
            'Kept account %s with %s (password unchanged).',
            $dryRun ? 'would then log in' : 'now logs in',
            $report['kept_login_email'],
        ));

        $this->line($dryRun ? 'Dry run — nothing was changed.' : 'Done.');

        return self::SUCCESS;
    }
}
