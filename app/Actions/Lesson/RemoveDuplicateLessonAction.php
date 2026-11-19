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
use App\Support\ImportValues;
use Illuminate\Support\Facades\DB;

/**
 * Take one duplicate lesson out of the diary in favour of the lesson kept
 * (lessons:remove-duplicates). Must run inside the caller's transaction.
 *
 * Refused (returns `skipped`) unless, checked live under lock: both lessons
 * exist and still match their reviewed CSV rows (status, date, start time,
 * order), the kept one is not cancelled, the pair still meets the scan's
 * duplicate rule (FindDuplicateLessonsAction::isGenuineDuplicate — same
 * instructor and pupil, and the same slot or a Drive Plus move of the kept
 * lesson from the duplicate's slot), the duplicate is on an imported (£0, no
 * Stripe) order, and the duplicate has no lesson payment or payout. Stripe-
 * linked records are never moved: Stripe invoice metadata and the invoice
 * webhook identify payments by lesson ID.
 *
 * Then, never changing an amount:
 * - GoRoadie finance rows (`instructor_finances.lesson_id`), the reflective
 *   log (when the kept lesson has none) and attached resources move to the
 *   kept lesson.
 * - Import mappings move to the kept lesson (and its diary item) only when the
 *   kept lesson is itself importer-created on an imported order. A Drive
 *   booking is never given an import mapping: that would mark it imported
 *   (summary overwrites, GoRoadie finance/reflective logs attaching, import
 *   repair treating it as imported). Otherwise the mappings stay on the
 *   cancelled duplicate and its deleted diary item — the GoRoadie ref stays
 *   mapped, so every import path still skips it.
 * - The duplicate is cancelled quietly (reason `other`, "Duplicate of lesson
 *   #…"), kept for history, and its diary item (and travel block) deleted
 *   unless another lesson shares it. No emails, notes or Head Office review
 *   (CancelBookingAction is not used for that reason).
 */
class RemoveDuplicateLessonAction
{
    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expectedRemove  The duplicate's reviewed CSV row
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expectedKeep  The kept lesson's reviewed CSV row
     * @return array{outcome: string, changes: array<string, mixed>, before: array<string, mixed>|null}
     */
    public function __invoke(int $removeLessonId, int $keepLessonId, array $expectedRemove, array $expectedKeep, string $groupId, bool $dryRun): array
    {
        $lessons = Lesson::query()
            ->whereKey([$removeLessonId, $keepLessonId])
            ->with('order')
            ->when(! $dryRun, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy('id');

        $remove = $lessons->get($removeLessonId);
        $keep = $lessons->get($keepLessonId);

        $refusal = $this->refusal($remove, $keep, $expectedRemove, $expectedKeep);

        if ($refusal !== null) {
            return ['outcome' => 'skipped: '.$refusal, 'changes' => [], 'before' => null];
        }

        $reflectiveLog = ReflectiveLog::query()->where('lesson_id', $remove->id)->first();
        $moveReflectiveLog = $reflectiveLog !== null && ! ReflectiveLog::query()->where('lesson_id', $keep->id)->exists();

        if ($reflectiveLog !== null && ! $moveReflectiveLog) {
            return ['outcome' => 'skipped: both lessons have a reflective log', 'changes' => [], 'before' => null];
        }

        $keepIsImported = $keep->order?->payment_mode === PaymentMode::IMPORTED
            && ImportMapping::wasImported(ImportMapping::ENTITY_LESSON, $keep->id);
        $keepResourceIds = DB::table('lesson_resource')->where('lesson_id', $keep->id)->pluck('resource_id')->all();
        $calendarItem = $remove->calendar_item_id !== null ? CalendarItem::query()->with(['calendar', 'travelItem'])->find($remove->calendar_item_id) : null;
        $deleteCalendarItem = $calendarItem !== null
            && $calendarItem->id !== $keep->calendar_item_id
            && ! Lesson::query()->where('calendar_item_id', $calendarItem->id)->whereKeyNot($remove->id)->exists();
        $lessonMappingIds = ImportMapping::query()->where('entity', ImportMapping::ENTITY_LESSON)->where('model_id', $remove->id)->pluck('id')->all();
        $diaryMappingIds = $calendarItem !== null
            ? ImportMapping::query()->where('entity', ImportMapping::ENTITY_DIARY)->where('model_id', $calendarItem->id)->pluck('id')->all()
            : [];
        $repointDiaryMappings = $keepIsImported && $deleteCalendarItem && $keep->calendar_item_id !== null;

        $changes = [
            'finance_ids_moved' => InstructorFinance::query()->where('lesson_id', $remove->id)->pluck('id')->all(),
            'reflective_log_id_moved' => $moveReflectiveLog ? $reflectiveLog->id : null,
            'resource_ids_moved' => DB::table('lesson_resource')->where('lesson_id', $remove->id)->whereNotIn('resource_id', $keepResourceIds)->pluck('resource_id')->all(),
            'kept_lesson_is_imported' => $keepIsImported,
            'lesson_mapping_ids_repointed' => $keepIsImported ? $lessonMappingIds : [],
            'lesson_mapping_ids_left_on_duplicate' => $keepIsImported ? [] : $lessonMappingIds,
            'diary_mapping_ids_repointed' => $repointDiaryMappings ? $diaryMappingIds : [],
            'diary_mapping_ids_left_on_deleted_item' => $repointDiaryMappings ? [] : $diaryMappingIds,
            'calendar_item_deleted' => $deleteCalendarItem ? $calendarItem->id : null,
            'travel_item_deleted' => $deleteCalendarItem ? $calendarItem->travelItem?->id : null,
            'lesson_cancelled' => $remove->id,
            'kept_lesson_id' => $keep->id,
        ];

        $itemColumns = ['id', 'calendar_id', 'parent_item_id', 'start_time', 'end_time', 'is_available', 'status', 'item_type', 'notes', 'unavailability_reason', 'travel_time_minutes'];
        $before = [
            'lesson' => $remove->only(['id', 'order_id', 'instructor_id', 'status', 'date', 'calendar_item_id', 'provisional_at', 'cancellation_reason', 'cancellation_reason_code', 'cancelled_at', 'student_lesson_number'])
                + ['start_time' => $remove->getRawOriginal('start_time'), 'end_time' => $remove->getRawOriginal('end_time')],
            'order' => $remove->order?->only(['id', 'status', 'package_lessons_count']),
            'calendar_item' => $calendarItem?->only($itemColumns),
            'travel_item' => $calendarItem?->travelItem?->only($itemColumns),
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
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expectedRemove
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expectedKeep
     */
    protected function refusal(?Lesson $remove, ?Lesson $keep, array $expectedRemove, array $expectedKeep): ?string
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

        foreach (['remove' => [$remove, $expectedRemove], 'keep' => [$keep, $expectedKeep]] as $label => [$lesson, $expected]) {
            if (trim($expected['date']) !== '' && ImportValues::date(trim($expected['date'])) === null) {
                return "the CSV date '{$expected['date']}' for lesson #{$lesson->id} isn't YYYY-MM-DD or DD/MM/YYYY — use the file as written by lessons:find-duplicates";
            }

            if (! $this->matchesCsv($lesson, $expected)) {
                return "lesson to {$label} #{$lesson->id} changed since the scan (status, date, time or order) — run lessons:find-duplicates again";
            }
        }

        if (! FindDuplicateLessonsAction::isGenuineDuplicate($remove, $keep, FindDuplicateLessonsAction::moveLogsForPupilOf($keep))) {
            return "not a duplicate of #{$keep->id}: different instructor or pupil, or a different date/start time with no Drive Plus move of #{$keep->id} from that time";
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

    /**
     * @param  array{status: string, date: string, start_time: string, order_id: string|int}  $expected
     */
    protected function matchesCsv(Lesson $lesson, array $expected): bool
    {
        $expectedDate = trim($expected['date']) === '' ? null : ImportValues::date(trim($expected['date']))?->toDateString();

        return $lesson->status->value === strtolower(trim($expected['status']))
            && $lesson->date?->toDateString() === $expectedDate
            && FindDuplicateLessonsAction::normaliseTime((string) $lesson->getRawOriginal('start_time')) === FindDuplicateLessonsAction::normaliseTime((string) $expected['start_time'])
            && (int) $lesson->order_id === (int) $expected['order_id'];
    }
}
