<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Instructor\SyncCashLessonFinanceEntryAction;
use App\Actions\Instructor\SyncPayoutFinanceEntryAction;
use App\Enums\LessonStatus;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Payout;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies lesson income into the instructor's finances: paid payouts, and
 * signed-off cash / alternative-payment lessons. Observes Payout, Lesson and
 * LessonPayment.
 *
 * Runs only after the surrounding transaction commits (so a rolled-back
 * sign-off or payout writes nothing) and never throws: a finance write must
 * not affect a payment that already succeeded. Anything missed here is picked
 * up by `finances:sync-lesson-income`.
 */
class LessonIncomeFinanceObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        protected SyncPayoutFinanceEntryAction $syncPayoutFinanceEntry,
        protected SyncCashLessonFinanceEntryAction $syncCashLessonFinanceEntry,
    ) {}

    public function saved(Model $model): void
    {
        $this->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->sync($model);
    }

    private function sync(Model $model): void
    {
        try {
            match (true) {
                $model instanceof Payout => ($this->syncPayoutFinanceEntry)($this->freshCopy($model)),
                $model instanceof Lesson => $this->syncLesson($model),
                $model instanceof LessonPayment => $this->syncLessonPayment($model),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('Failed to sync lesson income into instructor finances', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncLesson(Lesson $lesson): void
    {
        if (! $lesson->exists || $lesson->status === LessonStatus::COMPLETED || $lesson->wasChanged('status')) {
            ($this->syncCashLessonFinanceEntry)($this->freshCopy($lesson));
        }
    }

    /**
     * Sync from a fresh instance so relations loaded for the finance row are
     * never attached to the caller's model (and so never leak into the
     * caller's response). A deleted model is passed through as it is.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function freshCopy(Model $model): Model
    {
        return $model->exists ? ($model->fresh() ?? $model) : $model;
    }

    private function syncLessonPayment(LessonPayment $payment): void
    {
        $lesson = Lesson::query()->find($payment->lesson_id);

        if ($lesson?->status === LessonStatus::COMPLETED) {
            ($this->syncCashLessonFinanceEntry)($lesson);
        }
    }
}
