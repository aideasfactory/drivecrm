<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\LessonCancellationReason;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\InstructorFinance;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Payout;
use App\Models\ReflectiveLog;
use Illuminate\Support\Facades\DB;

/**
 * Take one duplicate lesson out of the diary in favour of the lesson kept
 * (lessons:remove-duplicates). Must run inside the caller's transaction.
 *
 * Refused (returns `skipped`) unless, checked live: both lessons exist, the
 * kept one is not cancelled, the duplicate still matches the reviewed CSV
 * (status, date, start time, order), both are the same instructor's and
 * pupil's, the duplicate is on an imported (£0, no Stripe) order, and the
 * duplicate has no lesson payment or payout. Stripe-linked records are never
 * moved: Stripe invoice metadata and the invoice webhook identify payments by
 * lesson ID.
 *
 * Then, never changing an amount:
 * - GoRoadie finance rows (`instructor_finances.lesson_id`), the reflective
 *   log (when the kept lesson has none) and attached resources move to the
 *   kept lesson.
 * - The duplicate's `lesson` import mappings point at the kept lesson, and the
 *   `diary` mapping of its diary item at the kept lesson's diary item, so the
 *   GoRoadie booking stays "already imported" and is never brought back.
 * - The duplicate is cancelled quietly (reason `other`, "Duplicate of lesson
 *   #…"), kept for history, and its diary item deleted (not reopened) unless
 *   the kept lesson or another lesson shares it. No emails, notes or Head
 *   Office review (CancelBookingAction is not used for that reason).
 */
class RemoveDuplicateLessonAction
{
    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expected  The duplicate as listed in the reviewed CSV
     * @return array{outcome: string, changes: array<string, mixed>, before: array<string, mixed>|null}
     */
    public function __invoke(int $removeLessonId, int $keepLessonId, array $expected, string $groupId, bool $dryRun): array
    {
        $lessons = Lesson::query()
            ->whereKey([$removeLessonId, $keepLessonId])
            ->with('order')
            ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy('id');

        $remove = $lessons->get($removeLessonId);
        $keep = $lessons->get($keepLessonId);

        $refusal = $this->refusal($remove, $keep, $expected);

        if ($refusal !== null) {
            return ['outcome' => 'skipped: '.$refusal, 'changes' => [], 'before' => null];
        }

        $reflectiveLog = ReflectiveLog::query()->where('lesson_id', $remove->id)->first();
        $moveReflectiveLog = $reflectiveLog !== null && ! ReflectiveLog::query()->where('lesson_id', $keep->id)->exists();

        if ($reflectiveLog !== null && ! $moveReflectiveLog) {
            return ['outcome' => 'skipped: both lessons have a reflective log', 'changes' => [], 'before' => null];
        }

        $keepResourceIds = DB::table('lesson_resource')->where('lesson_id', $keep->id)->pluck('resource_id')->all();
        $calendarItem = $remove->calendar_item_id !== null ? CalendarItem::query()->find($remove->calendar_item_id) : null;
        $deleteCalendarItem = $calendarItem !== null
            && $calendarItem->id !== $keep->calendar_item_id
            && ! Lesson::query()->where('calendar_item_id', $calendarItem->id)->whereKeyNot($remove->id)->exists();

        $changes = [
            'finance_ids_moved' => InstructorFinance::query()->where('lesson_id', $remove->id)->pluck('id')->all(),
            'reflective_log_id_moved' => $moveReflectiveLog ? $reflectiveLog->id : null,
            'resource_ids_moved' => DB::table('lesson_resource')->where('lesson_id', $remove->id)->whereNotIn('resource_id', $keepResourceIds)->pluck('resource_id')->all(),
            'lesson_mapping_ids_repointed' => ImportMapping::query()->where('entity', ImportMapping::ENTITY_LESSON)->where('model_id', $remove->id)->pluck('id')->all(),
            'diary_mapping_ids_repointed' => $deleteCalendarItem && $keep->calendar_item_id !== null
                ? ImportMapping::query()->where('entity', ImportMapping::ENTITY_DIARY)->where('model_id', $calendarItem->id)->pluck('id')->all()
                : [],
            'calendar_item_deleted' => $deleteCalendarItem ? $calendarItem->id : null,
            'lesson_cancelled' => $remove->id,
            'kept_lesson_id' => $keep->id,
        ];

        $before = [
            'lesson' => $remove->only(['id', 'order_id', 'instructor_id', 'status', 'date', 'calendar_item_id', 'provisional_at', 'cancellation_reason', 'cancellation_reason_code', 'cancelled_at', 'student_lesson_number'])
                + ['start_time' => $remove->getRawOriginal('start_time'), 'end_time' => $remove->getRawOriginal('end_time')],
            'calendar_item' => $calendarItem?->only(['id', 'calendar_id', 'start_time', 'end_time', 'is_available', 'status', 'item_type', 'notes', 'unavailability_reason', 'travel_time_minutes']),
            'calendar_date' => $calendarItem?->calendar?->date?->toDateString(),
        ];

        if ($dryRun) {
            return ['outcome' => 'would remove', 'changes' => $changes, 'before' => $before];
        }

        InstructorFinance::query()->whereKey($changes['finance_ids_moved'])->update(['lesson_id' => $keep->id]);

        if ($moveReflectiveLog) {
            ReflectiveLog::query()->whereKey($reflectiveLog->id)->update(['lesson_id' => $keep->id]);
        }

        DB::table('lesson_resource')
            ->where('lesson_id', $remove->id)
            ->whereIn('resource_id', $changes['resource_ids_moved'])
            ->update(['lesson_id' => $keep->id, 'updated_at' => now()]);

        ImportMapping::query()->whereKey($changes['lesson_mapping_ids_repointed'])->update(['model_id' => $keep->id]);
        ImportMapping::query()->whereKey($changes['diary_mapping_ids_repointed'])->update(['model_id' => $keep->calendar_item_id]);

        $remove->status = LessonStatus::CANCELLED;
        $remove->cancellation_reason_code = LessonCancellationReason::Other;
        $remove->cancellation_reason = "Duplicate of lesson #{$keep->id} (duplicate cleanup {$groupId})";
        $remove->cancelled_at = now();
        $remove->provisional_at = null;
        $remove->calendar_item_id = null;
        $remove->save();

        if ($deleteCalendarItem) {
            ($this->deleteCalendarItem)($calendarItem->fresh());
        }

        return ['outcome' => 'removed', 'changes' => $changes, 'before' => $before];
    }

    /**
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expected
     */
    protected function refusal(?Lesson $remove, ?Lesson $keep, array $expected): ?string
    {
        if ($remove === null) {
            return 'lesson to remove no longer exists';
        }

        if ($keep === null) {
            return 'lesson to keep no longer exists';
        }

        if ($remove->status === LessonStatus::CANCELLED) {
            return 'already cancelled';
        }

        if ($keep->status === LessonStatus::CANCELLED) {
            return "lesson to keep #{$keep->id} is cancelled";
        }

        $startTime = FindDuplicateLessonsAction::normaliseTime((string) $remove->getRawOriginal('start_time'));

        if ($remove->status->value !== $expected['status']
            || $remove->date?->toDateString() !== $expected['date']
            || $startTime !== FindDuplicateLessonsAction::normaliseTime($expected['start_time'])
            || (int) $remove->order_id !== (int) $expected['order_id']) {
            return 'changed since the scan (status, date, time or order) — run lessons:find-duplicates again';
        }

        if ((int) $remove->instructor_id !== (int) $keep->instructor_id) {
            return 'different instructor from the lesson to keep';
        }

        if ((int) $remove->order?->student_id !== (int) $keep->order?->student_id) {
            return 'different pupil from the lesson to keep';
        }

        if ($remove->order?->payment_mode !== PaymentMode::IMPORTED) {
            return 'not on an imported (£0) order';
        }

        if (LessonPayment::query()->where('lesson_id', $remove->id)->exists()) {
            return 'has a lesson payment';
        }

        if (Payout::query()->where('lesson_id', $remove->id)->exists()) {
            return 'has a payout';
        }

        return null;
    }
}
