<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Enums\LessonCancellationReason;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Models\ImportMapping;
use App\Models\Lesson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResolveImportedLessonClashesAction
{
    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
        protected SyncImportedOrderAction $syncImportedOrder,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
    ) {}

    /**
     * Deal with future imported lessons whose diary time overlaps a lesson
     * booked in Drive (not imported, not cancelled). The Drive lesson is
     * never touched.
     *
     * - Provable duplicate: the overlapping Drive lesson is the same pupil's
     *   and confirmed (pending or completed, not a draft hold). The imported
     *   copy is cancelled quietly — kept for history with the reason
     *   "Duplicate of Drive lesson #…", out of the diary (its diary item is
     *   removed, not reopened), the order synced and the pupil's lessons
     *   renumbered. No emails, notes or Stripe (CancelBookingAction is not
     *   used for that reason).
     * - Anything else (a different pupil, or the Drive booking is only a
     *   draft): the imported lesson is marked provisional so it shows in the
     *   Clashing lessons report and the office decides.
     *
     * Only importer-created, pending, dated lessons on a `payment_mode =
     * imported` (£0) order are considered. A duplicate with a lesson payment,
     * payout or reflective log is flagged instead of cancelled.
     *
     * @return array{counts: array{clashing: int, cancelled: int, flagged: int, already_flagged: int, skipped: int}, lessons: array<int, array{lesson_id: int, date: string, time: string, instructor_id: int, student_id: int|null, drive_lesson_ids: string, drive_student_ids: string, outcome: string}>, dates_by_instructor: array<int, array<string, true>>}
     */
    public function __invoke(bool $dryRun): array
    {
        $result = [
            'counts' => ['clashing' => 0, 'cancelled' => 0, 'flagged' => 0, 'already_flagged' => 0, 'skipped' => 0],
            'lessons' => [],
            'dates_by_instructor' => [],
        ];

        foreach ($this->findClashes() as $lessonId => $driveLessons) {
            $lesson = Lesson::query()->with(['order', 'calendarItem'])->find($lessonId);

            if ($lesson === null) {
                continue;
            }

            $result['counts']['clashing']++;
            $outcome = $this->resolve($lesson, $driveLessons, $dryRun);
            $result['counts'][$outcome === 'cancelled' || $outcome === 'flagged' || $outcome === 'already_flagged' ? $outcome : 'skipped']++;

            if ($outcome === 'cancelled' && ! $dryRun) {
                $result['dates_by_instructor'][(int) $lesson->instructor_id][(string) $driveLessons[0]->date] = true;
            }

            $result['lessons'][] = [
                'lesson_id' => $lesson->id,
                'date' => (string) $driveLessons[0]->date,
                'time' => substr((string) $driveLessons[0]->start_time, 0, 5).'–'.substr((string) $driveLessons[0]->end_time, 0, 5),
                'instructor_id' => (int) $lesson->instructor_id,
                'student_id' => $lesson->order?->student_id,
                'drive_lesson_ids' => implode(', ', array_map(fn ($drive) => $drive->drive_lesson_id.' ('.$drive->drive_status.')', $driveLessons)),
                'drive_student_ids' => implode(', ', array_unique(array_map(fn ($drive) => (string) $drive->drive_student_id, $driveLessons))),
                'outcome' => $outcome,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, object>  $driveLessons
     */
    protected function resolve(Lesson $lesson, array $driveLessons, bool $dryRun): string
    {
        if ($lesson->order?->payment_mode !== PaymentMode::IMPORTED || $lesson->status !== LessonStatus::PENDING
            || $lesson->banked_at !== null || $lesson->date === null || $lesson->calendarItem === null) {
            return 'skipped: not an open imported lesson';
        }

        $duplicateOf = collect($driveLessons)->first(fn ($drive): bool => (int) $drive->drive_student_id === (int) $lesson->order->student_id
            && in_array($drive->drive_status, [LessonStatus::PENDING->value, LessonStatus::COMPLETED->value], true));

        $hasRecords = $lesson->lessonPayment()->exists() || $lesson->payout()->exists() || $lesson->reflectiveLog()->exists();

        if ($duplicateOf !== null && ! $hasRecords) {
            if (! $dryRun) {
                $this->cancelDuplicate($lesson, (int) $duplicateOf->drive_lesson_id);
            }

            return 'cancelled';
        }

        if ($lesson->provisional_at !== null) {
            return 'already_flagged';
        }

        if (! $dryRun) {
            $lesson->update(['provisional_at' => now()]);

            Log::info('import:repair flagged an imported lesson clashing with a Drive lesson as provisional', [
                'lesson_id' => $lesson->id,
                'drive_lesson_ids' => array_map(fn ($drive) => (int) $drive->drive_lesson_id, $driveLessons),
            ]);
        }

        return 'flagged';
    }

    protected function cancelDuplicate(Lesson $lesson, int $driveLessonId): void
    {
        DB::transaction(function () use ($lesson, $driveLessonId): void {
            $calendarItem = $lesson->calendarItem;
            $order = $lesson->order;

            $lesson->status = LessonStatus::CANCELLED;
            $lesson->cancellation_reason_code = LessonCancellationReason::Other;
            $lesson->cancellation_reason = "Duplicate of Drive lesson #{$driveLessonId} (import repair)";
            $lesson->cancelled_at = now();
            $lesson->provisional_at = null;
            $lesson->calendar_item_id = null;
            $lesson->save();

            // The imported diary item sits on top of the Drive lesson: removed, never reopened as availability.
            ($this->deleteCalendarItem)($calendarItem->fresh());

            ($this->syncImportedOrder)($order->fresh());
            ($this->recalculateStudentLessonNumbers)((int) $order->student_id);
        });

        Log::info('import:repair cancelled an imported lesson duplicating a Drive lesson', [
            'lesson_id' => $lesson->id,
            'drive_lesson_id' => $driveLessonId,
            'order_id' => $lesson->order_id,
            'calendar_item_id' => $lesson->getOriginal('calendar_item_id'),
        ]);
    }

    /**
     * Imported, pending lessons dated today or later, each with the Drive
     * lessons (not imported, not cancelled) their diary time overlaps.
     *
     * @return array<int, array<int, object>>
     */
    protected function findClashes(): array
    {
        return DB::table('lessons as imported')
            ->join('import_mappings as mapping', fn ($join) => $join
                ->on('mapping.model_id', '=', 'imported.id')
                ->where('mapping.entity', ImportMapping::ENTITY_LESSON))
            ->join('calendar_items as imported_item', 'imported_item.id', '=', 'imported.calendar_item_id')
            ->join('calendars', 'calendars.id', '=', 'imported_item.calendar_id')
            ->join('calendar_items as drive_item', fn ($join) => $join
                ->on('drive_item.calendar_id', '=', 'imported_item.calendar_id')
                ->on('drive_item.id', '<>', 'imported_item.id')
                ->on('drive_item.start_time', '<', 'imported_item.end_time')
                ->on('drive_item.end_time', '>', 'imported_item.start_time'))
            ->join('lessons as drive', 'drive.calendar_item_id', '=', 'drive_item.id')
            ->join('orders as drive_order', 'drive_order.id', '=', 'drive.order_id')
            ->where('imported.status', LessonStatus::PENDING->value)
            ->where('calendars.date', '>=', now()->toDateString())
            ->where('drive.status', '<>', LessonStatus::CANCELLED->value)
            ->whereNotExists(fn ($query) => $query
                ->from('import_mappings as drive_mapping')
                ->whereColumn('drive_mapping.model_id', 'drive.id')
                ->where('drive_mapping.entity', ImportMapping::ENTITY_LESSON))
            ->orderBy('calendars.date')
            ->orderBy('imported_item.start_time')
            ->get([
                'imported.id as lesson_id',
                'calendars.date',
                'imported_item.start_time',
                'imported_item.end_time',
                'drive.id as drive_lesson_id',
                'drive.status as drive_status',
                'drive_order.student_id as drive_student_id',
            ])
            ->groupBy('lesson_id')
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }
}
