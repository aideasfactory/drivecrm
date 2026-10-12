<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ItsaObligationStatus;
use App\Models\HmrcItsaObligation;
use App\Models\HmrcToken;
use App\Models\HmrcVatObligation;
use App\Models\User;
use App\Notifications\ItsaObligationDueSoon;
use App\Notifications\VatObligationDueSoon;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncHmrcItsaObligations extends Command
{
    protected $signature = 'hmrc:sync-itsa-obligations {--dry-run : Show what would be synced/notified without doing it}';

    protected $description = 'Queue ITSA and VAT deadline reminders from stored obligations. Unattended HMRC pulls are skipped so WEB_APP_VIA_SERVER fraud headers are only sent from a browser session.';

    /**
     * Days-out thresholds at which we send reminder notifications.
     */
    private const REMINDER_THRESHOLDS = [30, 14, 7, 1];

    public function handle(PushNotificationService $push): int
    {
        $synced = 0;
        $notified = 0;

        HmrcToken::query()
            ->with('user.instructor')
            ->chunkById(100, function ($tokens) use ($push, &$synced, &$notified): void {
                foreach ($tokens as $token) {
                    $user = $token->user;
                    $instructor = $user?->instructor;
                    if ($user === null || $instructor === null) {
                        continue;
                    }

                    $itsaApplies = in_array($instructor->business_type?->value, ['sole_trader', 'partnership'], true);
                    $vatApplies = (bool) $instructor->vat_registered && is_string($instructor->vrn) && $instructor->vrn !== '';

                    if (! $itsaApplies && ! $vatApplies) {
                        continue;
                    }

                    if ($itsaApplies) {
                        $this->skipUnattendedPull('itsa', $user);
                        $notified += $this->dispatchItsaReminders($user, $push);
                    }

                    if ($vatApplies) {
                        $this->skipUnattendedPull('vat', $user);
                        $notified += $this->dispatchVatReminders($user, $push);
                    }

                    $synced++;
                }
            });

        Log::info('HMRC obligations sync finished', [
            'synced' => $synced,
            'notified' => $notified,
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        $this->info("Checked {$synced} instructor(s); queued {$notified} reminder(s). Remote HMRC pulls were skipped.");

        return Command::SUCCESS;
    }

    /**
     * A scheduled pull has no originating browser, so it cannot supply
     * Gov-Client-Public-IP, port, or a fresh device fingerprint. Sending
     * WEB_APP_VIA_SERVER headers from here would be the request HMRC records.
     */
    private function skipUnattendedPull(string $service, User $user): void
    {
        $message = "skip unattended {$service} pull user_id={$user->id}";

        if ($this->option('dry-run')) {
            $this->line($message);

            return;
        }

        Log::info('Skipped unattended HMRC obligation pull. Refresh from the instructor session so fraud-prevention headers match WEB_APP_VIA_SERVER.', [
            'service' => $service,
            'user_id' => $user->id,
        ]);
    }

    private function dispatchItsaReminders(User $user, PushNotificationService $push): int
    {
        $count = 0;
        $now = now();

        // HMRC (especially sandbox) leaves years of still-Open periods on file.
        // Reminders are only for deadlines that have not already passed.
        $obligations = HmrcItsaObligation::query()
            ->where('user_id', $user->id)
            ->where('status', ItsaObligationStatus::Open)
            ->whereDate('due_date', '>=', $now->toDateString())
            ->get();

        foreach ($obligations as $obligation) {
            $days = $obligation->daysUntilDue();
            $threshold = $this->thresholdHit($days);

            if ($threshold === null) {
                continue;
            }

            if (
                $obligation->last_reminder_sent_at !== null
                && $obligation->last_reminder_sent_at->diffInDays($now) < $threshold
            ) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("would-notify-itsa user_id={$user->id} period_key={$obligation->period_key} days={$days}");
                $count++;

                continue;
            }

            $user->notify(new ItsaObligationDueSoon($obligation, $days));
            $push->queueAndSend(
                $user,
                'MTD ITSA — quarterly update due soon',
                $this->dueSoonPushBody('quarterly update', $days),
                ['route' => '/hmrc/itsa'],
            );

            $obligation->forceFill(['last_reminder_sent_at' => $now])->save();
            $count++;
        }

        return $count;
    }

    private function dispatchVatReminders(User $user, PushNotificationService $push): int
    {
        $count = 0;
        $now = now();

        $obligations = HmrcVatObligation::query()
            ->where('user_id', $user->id)
            ->where('status', ItsaObligationStatus::Open)
            ->whereDate('due_date', '>=', $now->toDateString())
            ->get();

        foreach ($obligations as $obligation) {
            $days = $obligation->daysUntilDue();
            $threshold = $this->thresholdHit($days);

            if ($threshold === null) {
                continue;
            }

            if (
                $obligation->last_reminder_sent_at !== null
                && $obligation->last_reminder_sent_at->diffInDays($now) < $threshold
            ) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("would-notify-vat user_id={$user->id} period_key={$obligation->period_key} days={$days}");
                $count++;

                continue;
            }

            $user->notify(new VatObligationDueSoon($obligation, $days));
            $push->queueAndSend(
                $user,
                'MTD VAT — return due soon',
                $this->dueSoonPushBody('VAT return', $days),
                ['route' => '/hmrc/vat'],
            );

            $obligation->forceFill(['last_reminder_sent_at' => $now])->save();
            $count++;
        }

        return $count;
    }

    /**
     * Tightest upcoming deadline window that still applies.
     * Overdue / historical periods (negative days) are never "due soon".
     *
     * @return int|null Matching threshold (1, 7, 14, or 30), or null to skip.
     */
    private function thresholdHit(int $days): ?int
    {
        if ($days < 0) {
            return null;
        }

        $thresholds = self::REMINDER_THRESHOLDS;
        sort($thresholds);

        foreach ($thresholds as $threshold) {
            if ($days <= $threshold) {
                return $threshold;
            }
        }

        return null;
    }

    private function dueSoonPushBody(string $subject, int $days): string
    {
        return match (true) {
            $days <= 0 => "Your {$subject} is due today. Tap to open.",
            $days === 1 => "Your {$subject} is due tomorrow. Tap to open.",
            default => "Your {$subject} is due in {$days} day(s). Tap to open.",
        };
    }
}
