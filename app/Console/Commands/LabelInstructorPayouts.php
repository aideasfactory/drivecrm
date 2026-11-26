<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PayoutLabelService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class LabelInstructorPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:label-instructor-payouts
        {--dry-run : List the labels that would be written without calling Stripe}
        {--instructor= : Only this instructor id}
        {--since= : Only payouts created on or after this date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-off: label past lesson payouts in instructors\' Stripe accounts with the learner, lesson date and booking number. Text only, never moves money. Safe to re-run.';

    public function handle(PayoutLabelService $payoutLabelService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $instructorId = $this->option('instructor') !== null ? (int) $this->option('instructor') : null;
        $since = $this->option('since') !== null ? CarbonImmutable::parse((string) $this->option('since'))->startOfDay() : null;

        $results = $payoutLabelService->labelPastPayouts($dryRun, $instructorId, $since);

        if ($results === []) {
            $this->line('No Stripe payouts to label.');

            return self::SUCCESS;
        }

        $this->table(
            ['Payout', 'Instructor', 'Label', 'Result'],
            array_map(fn (array $row): array => [
                $row['payout_id'],
                $row['instructor'] ?? '—',
                $row['description'],
                $row['labelled'] === null ? 'dry run' : ($row['labelled'] ? 'labelled' : 'FAILED (see log)'),
            ], $results),
        );

        $failed = count(array_filter($results, fn (array $row): bool => $row['labelled'] === false));

        $this->line($dryRun
            ? sprintf('Dry run — %d payout(s) would be labelled. Nothing was sent to Stripe.', count($results))
            : sprintf('Labelled %d payout(s), %d failed.', count($results) - $failed, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
