<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Instructor\SyncCashLessonFinanceEntryAction;
use App\Actions\Instructor\SyncPayoutFinanceEntryAction;
use App\Actions\Instructor\UpsertAutomaticFinanceEntryAction;
use App\Enums\FinanceSource;
use App\Enums\LessonPaymentMethod;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\InstructorFinance;
use App\Models\Lesson;
use App\Models\Payout;
use Illuminate\Console\Command;
use Throwable;

class SyncLessonIncomeFinances extends Command
{
    /**
     * @var string
     */
    protected $signature = 'finances:sync-lesson-income
        {--instructor= : Only sync this instructor ID}
        {--dry-run : Report what would be written without saving}';

    /**
     * @var string
     */
    protected $description = 'Backfill and reconcile automatic finance income: one read-only payment row per paid payout and per signed-off cash / alternative-payment lesson. Safe to run repeatedly.';

    public function handle(
        SyncPayoutFinanceEntryAction $syncPayoutFinanceEntry,
        SyncCashLessonFinanceEntryAction $syncCashLessonFinanceEntry,
        UpsertAutomaticFinanceEntryAction $upsertAutomaticFinanceEntry,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $instructorId = $this->option('instructor') !== null ? (int) $this->option('instructor') : null;
        $failures = 0;

        $payouts = Payout::query()
            ->where('status', PayoutStatus::PAID)
            ->when($instructorId !== null, fn ($query) => $query->where('instructor_id', $instructorId));

        $cashLessons = Lesson::query()
            ->where('status', LessonStatus::COMPLETED)
            ->whereDoesntHave('payout')
            ->where(fn ($query) => $query
                ->whereHas('order', fn ($order) => $order->where('payment_mode', PaymentMode::CASH))
                ->orWhereHas('lessonPayment', fn ($payment) => $payment->where('payment_method', LessonPaymentMethod::ALTERNATIVE)))
            ->when($instructorId !== null, fn ($query) => $query->where('instructor_id', $instructorId));

        $automaticRows = InstructorFinance::query()
            ->automatic()
            ->when($instructorId !== null, fn ($query) => $query->where('instructor_id', $instructorId));

        if ($dryRun) {
            $existingPayoutIds = (clone $automaticRows)->where('source', FinanceSource::StripePayout->value)->pluck('source_id')->all();
            $existingLessonIds = (clone $automaticRows)->where('source', FinanceSource::CashLesson->value)->pluck('source_id')->all();

            $missingPayouts = (clone $payouts)->whereNotIn('id', $existingPayoutIds)->count();
            $missingLessons = (clone $cashLessons)->whereNotIn('id', $existingLessonIds)->count();

            $this->info("Dry run: {$missingPayouts} paid payout(s) and {$missingLessons} cash / alternative lesson(s) have no finance row yet. ".$automaticRows->count().' automatic row(s) already exist and would be re-checked.');

            return self::SUCCESS;
        }

        $payoutCount = 0;
        foreach ($payouts->lazyById() as $payout) {
            try {
                $syncPayoutFinanceEntry($payout);
                $payoutCount++;
            } catch (Throwable $e) {
                $failures++;
                $this->error("Payout #{$payout->id}: {$e->getMessage()}");
            }
        }

        $lessonCount = 0;
        foreach ($cashLessons->lazyById() as $lesson) {
            try {
                $syncCashLessonFinanceEntry($lesson);
                $lessonCount++;
            } catch (Throwable $e) {
                $failures++;
                $this->error("Lesson #{$lesson->id}: {$e->getMessage()}");
            }
        }

        $released = 0;
        foreach ($automaticRows->lazyById() as $finance) {
            try {
                if ($this->recheck($finance, $syncPayoutFinanceEntry, $syncCashLessonFinanceEntry, $upsertAutomaticFinanceEntry) === null) {
                    $released++;
                }
            } catch (Throwable $e) {
                $failures++;
                $this->error("Finance #{$finance->id}: {$e->getMessage()}");
            }
        }

        $this->info("Synced {$payoutCount} payout(s) and {$lessonCount} cash / alternative lesson(s). Removed or unlinked {$released} row(s) whose source no longer counts as income. Failures: {$failures}.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Re-run the sync for an existing automatic row's source, so rows whose
     * payout or lesson was deleted or no longer qualifies are cleaned up.
     */
    private function recheck(
        InstructorFinance $finance,
        SyncPayoutFinanceEntryAction $syncPayoutFinanceEntry,
        SyncCashLessonFinanceEntryAction $syncCashLessonFinanceEntry,
        UpsertAutomaticFinanceEntryAction $upsertAutomaticFinanceEntry,
    ): ?InstructorFinance {
        $sourceId = (int) $finance->source_id;

        $source = match ($finance->source) {
            FinanceSource::StripePayout => Payout::query()->find($sourceId),
            FinanceSource::CashLesson => Lesson::query()->find($sourceId),
            default => null,
        };

        return match (true) {
            $source instanceof Payout => $syncPayoutFinanceEntry($source),
            $source instanceof Lesson => $syncCashLessonFinanceEntry($source),
            default => $upsertAutomaticFinanceEntry((int) $finance->instructor_id, $finance->source, $sourceId, null),
        };
    }
}
