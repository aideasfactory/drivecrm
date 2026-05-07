<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\HmrcTokenRefreshOutcome;
use App\Models\HmrcTokenRefreshLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckHmrcRefreshHealth extends Command
{
    protected $signature = 'hmrc:check-refresh-health
        {--hours=24 : Lookback window in hours}
        {--threshold=1.0 : Failure-rate percentage that triggers a warning}';

    protected $description = 'Compute the HMRC token-refresh failure rate over a recent window. Exits non-zero (and logs a warning suitable for alerting) when the rate exceeds the threshold.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $threshold = (float) $this->option('threshold');

        $since = Carbon::now()->subHours($hours);

        $rows = HmrcTokenRefreshLog::query()
            ->where('attempted_at', '>=', $since)
            ->selectRaw('outcome, error_code, count(*) as attempts')
            ->groupBy('outcome', 'error_code')
            ->get();

        $total = (int) $rows->sum('attempts');

        if ($total === 0) {
            $this->info("No HMRC refresh attempts in the last {$hours}h.");

            return Command::SUCCESS;
        }

        $failures = (int) $rows
            ->reject(fn ($row) => $row->outcome === HmrcTokenRefreshOutcome::Success)
            ->sum('attempts');

        $rate = ($failures / $total) * 100;

        $byOutcome = $rows
            ->groupBy(fn ($row) => $row->outcome->value)
            ->map(fn ($group) => (int) $group->sum('attempts'))
            ->toArray();

        $topErrorCodes = $rows
            ->reject(fn ($row) => $row->outcome === HmrcTokenRefreshOutcome::Success || $row->error_code === null)
            ->groupBy('error_code')
            ->map(fn ($group) => (int) $group->sum('attempts'))
            ->sortDesc()
            ->take(3)
            ->toArray();

        $context = [
            'window_hours' => $hours,
            'since' => $since->toIso8601String(),
            'total_attempts' => $total,
            'failures' => $failures,
            'failure_rate_pct' => round($rate, 3),
            'threshold_pct' => $threshold,
            'by_outcome' => $byOutcome,
            'top_error_codes' => $topErrorCodes,
        ];

        $this->table(
            ['metric', 'value'],
            [
                ['window_hours', (string) $hours],
                ['total_attempts', (string) $total],
                ['failures', (string) $failures],
                ['failure_rate_pct', sprintf('%.3f', $rate)],
                ['threshold_pct', sprintf('%.3f', $threshold)],
            ]
        );

        if ($rate > $threshold) {
            Log::warning('HMRC refresh failure rate exceeded threshold', $context);

            $this->error(sprintf(
                'HMRC refresh failure rate %.3f%% exceeded threshold %.3f%% over the last %dh (failures=%d/%d).',
                $rate,
                $threshold,
                $hours,
                $failures,
                $total,
            ));

            return Command::FAILURE;
        }

        Log::info('HMRC refresh health check passed', $context);

        $this->info(sprintf(
            'HMRC refresh failure rate %.3f%% within threshold %.3f%% (failures=%d/%d, %dh).',
            $rate,
            $threshold,
            $failures,
            $total,
            $hours,
        ));

        return Command::SUCCESS;
    }
}
