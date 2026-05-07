<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HmrcToken;
use App\Notifications\HmrcReconnectSoonNotification;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorHmrcTokenExpiry extends Command
{
    protected $signature = 'hmrc:monitor-token-expiry {--dry-run : Show what would be sent without notifying}';

    protected $description = 'Notify instructors when their HMRC refresh token approaches expiry (T-30 / T-7).';

    public function handle(PushNotificationService $push): int
    {
        $thresholds = (array) config('hmrc.expiry_warning_days', [30, 7]);
        sort($thresholds);

        $now = now();
        $sent = 0;

        HmrcToken::query()
            ->with('user')
            ->whereNotNull('refresh_expires_at')
            ->where('refresh_expires_at', '>', $now)
            ->chunkById(100, function ($tokens) use (&$sent, $thresholds, $now, $push): void {
                foreach ($tokens as $token) {
                    /** @var HmrcToken $token */
                    $days = (int) ceil($now->diffInDays($token->refresh_expires_at, false));
                    $threshold = $this->thresholdHit($days, $thresholds);

                    if ($threshold === null) {
                        continue;
                    }

                    if ($token->last_expiry_warning_at && $token->last_expiry_warning_at->diffInDays($now) < $threshold) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("would-notify user_id={$token->user_id} days={$days} threshold={$threshold}");

                        continue;
                    }

                    $user = $token->user;

                    if (! $user) {
                        continue;
                    }

                    $user->notify(new HmrcReconnectSoonNotification($days));
                    $push->queueAndSend(
                        $user,
                        'HMRC connection — action soon',
                        "Your HMRC connection will need renewing in {$days} day(s). Tap to reconnect.",
                        ['route' => '/hmrc'],
                    );

                    $token->forceFill(['last_expiry_warning_at' => $now])->save();
                    $sent++;
                }
            });

        Log::info('HMRC token expiry monitor finished', ['sent' => $sent, 'dry_run' => (bool) $this->option('dry-run')]);

        $this->info("Notified {$sent} instructor(s).");

        return Command::SUCCESS;
    }

    /**
     * @param  array<int, int>  $thresholds  Ascending list, e.g. [7, 30]
     */
    private function thresholdHit(int $days, array $thresholds): ?int
    {
        foreach ($thresholds as $threshold) {
            if ($days <= $threshold) {
                return $threshold;
            }
        }

        return null;
    }
}
