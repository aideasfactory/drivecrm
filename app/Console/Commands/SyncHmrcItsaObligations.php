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
use App\Services\HmrcItsaService;
use App\Services\HmrcVatService;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncHmrcItsaObligations extends Command
{
    protected $signature = 'hmrc:sync-itsa-obligations {--dry-run : Show what would be synced/notified without doing it}';

    protected $description = 'Refresh ITSA + VAT obligations from HMRC for every connected instructor and queue deadline reminders.';

    /**
     * Days-out thresholds at which we send reminder notifications.
     */
    private const REMINDER_THRESHOLDS = [30, 14, 7, 1];

    public function handle(HmrcItsaService $itsa, HmrcVatService $vat, PushNotificationService $push): int
    {
        $synced = 0;
        $notified = 0;

        HmrcToken::query()
            ->with('user.instructor')
            ->chunkById(100, function ($tokens) use ($itsa, $vat, $push, &$synced, &$notified): void {
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
                        if ($this->option('dry-run')) {
                            $this->line("would-sync-itsa user_id={$user->id}");
                        } else {
                            try {
                                $itsa->syncObligations($user);
                            } catch (Throwable $exception) {
                                Log::warning('ITSA sync failed for user', [
                                    'user_id' => $user->id,
                                    'error' => $exception->getMessage(),
                                ]);
                            }
                        }
                        $notified += $this->dispatchItsaReminders($user, $push);
                    }

                    if ($vatApplies) {
                        if ($this->option('dry-run')) {
                            $this->line("would-sync-vat user_id={$user->id}");
                        } else {
                            try {
                                $vat->syncObligations($user);
                            } catch (Throwable $exception) {
                                Log::warning('VAT sync failed for user', [
                                    'user_id' => $user->id,
                                    'error' => $exception->getMessage(),
                                ]);
                            }
                        }
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

        $this->info("Synced {$synced} instructor(s); queued {$notified} reminder(s).");

        return Command::SUCCESS;
    }

    private function dispatchItsaReminders(User $user, PushNotificationService $push): int
    {
        $count = 0;
        $now = now();

        $obligations = HmrcItsaObligation::query()
            ->where('user_id', $user->id)
            ->where('status', ItsaObligationStatus::Open)
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
                "Your quarterly update is due in {$days} day(s). Tap to open.",
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
                "Your VAT return is due in {$days} day(s). Tap to open.",
                ['route' => '/hmrc/vat'],
            );

            $obligation->forceFill(['last_reminder_sent_at' => $now])->save();
            $count++;
        }

        return $count;
    }

    private function thresholdHit(int $days): ?int
    {
        $thresholds = self::REMINDER_THRESHOLDS;
        sort($thresholds);

        foreach ($thresholds as $threshold) {
            if ($days <= $threshold) {
                return $threshold;
            }
        }

        return null;
    }
}
