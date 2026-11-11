<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Support\ImportValues;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class CompleteImportedLessonAction
{
    /**
     * Quietly mark a lesson imported on an earlier run as completed when the
     * old system's latest export says it happened. Every condition must hold,
     * so a lesson booked through Drive+ can never match:
     *
     * - the diary row resolves to `completed` (explicit, or blank + past date);
     * - the lesson was created by the importer (`lesson` mapping, action `created`);
     * - it is on an imported order (`payment_mode = imported`);
     * - it is still pending, not banked, and dated before today;
     * - its date matches the row's date (a lesson moved in either system is left alone);
     * - it belongs to the row's instructor.
     *
     * Same writes as the importer for a past lesson — lesson completed (at its
     * end time) and its calendar item completed. No sign-off pipeline: no
     * emails, notifications, payouts or lesson payments.
     *
     * @param  array<string, string|int>  $row
     * @param  Collection<string, Lesson>  $candidates  From candidatesFor() for the row's instructor
     * @return Lesson|null The lesson completed, or null when nothing matched
     */
    public function __invoke(array $row, Collection $candidates): ?Lesson
    {
        $ref = trim((string) $row['diary_ref']);
        $date = $this->completedRowDate($row);
        $lesson = $candidates->get($ref);

        if ($date === null || ! $lesson || $lesson->date->toDateString() !== $date) {
            return null;
        }

        $candidates->forget($ref);

        return $this->complete($lesson) ? $lesson : null;
    }

    /**
     * Complete one eligible lesson: lesson completed (at its end time) and its
     * calendar item completed. Eligibility is re-checked in the update itself,
     * so a lesson signed off meanwhile is left alone.
     *
     * @return bool Whether the lesson was completed
     */
    public function complete(Lesson $lesson): bool
    {
        $updated = $this->eligible()
            ->whereKey($lesson->id)
            ->update([
                'status' => LessonStatus::COMPLETED,
                'completed_at' => $lesson->end_time ? $lesson->date->copy()->setTimeFrom($lesson->end_time) : $lesson->date,
            ]);

        if ($updated === 0) {
            return false;
        }

        if ($lesson->calendar_item_id) {
            CalendarItem::query()
                ->whereKey($lesson->calendar_item_id)
                ->update(['status' => CalendarItemStatus::COMPLETED]);
        }

        return true;
    }

    /**
     * Every lesson the importer created that could be completed: on an
     * imported order, pending, not banked, dated before today. Used by
     * import:lessons to close off past imported lessons whatever the file says.
     *
     * @return EloquentCollection<int, Lesson>
     */
    public function pastPendingLessons(): EloquentCollection
    {
        return $this->candidates()->get();
    }

    /**
     * The instructor's lessons that could be completed, keyed by diary ref.
     *
     * @return Collection<string, Lesson>
     */
    public function candidatesFor(Instructor $instructor): Collection
    {
        return $this->candidates()
            ->where('lessons.instructor_id', $instructor->id)
            ->get()
            ->keyBy('import_ref');
    }

    /**
     * How many diary rows would complete a lesson (for the check step — writes nothing).
     *
     * @param  array<int, array<string, string|int>>  $rows
     * @param  array<string, int>  $instructorIds  instructor_ref => instructor id, for instructors that already exist
     */
    public function countCompletable(array $rows, array $instructorIds): int
    {
        $candidates = $this->candidates()->get()->keyBy('import_ref');
        $count = 0;

        foreach ($rows as $row) {
            $lesson = $candidates->get(trim((string) ($row['diary_ref'] ?? '')));
            $instructorId = $instructorIds[trim((string) ($row['instructor_ref'] ?? ''))] ?? null;

            if ($lesson
                && $lesson->instructor_id === $instructorId
                && $lesson->date->toDateString() === $this->completedRowDate($row)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Eligible lessons the importer created, with their diary ref as `import_ref`.
     *
     * @return Builder<Lesson>
     */
    protected function candidates(): Builder
    {
        return $this->eligible()
            ->join('import_mappings', function ($join): void {
                $join->on('import_mappings.model_id', '=', 'lessons.id')
                    ->where('import_mappings.entity', ImportMapping::ENTITY_LESSON)
                    ->where('import_mappings.action', ImportMapping::ACTION_CREATED);
            })
            ->select('lessons.*', 'import_mappings.source_ref as import_ref');
    }

    /**
     * Pending, dated, not banked lessons before today on imported orders.
     *
     * @return Builder<Lesson>
     */
    protected function eligible(): Builder
    {
        return Lesson::query()
            ->where('lessons.status', LessonStatus::PENDING)
            ->whereNull('lessons.banked_at')
            ->whereNotNull('lessons.date')
            ->whereDate('lessons.date', '<', now()->toDateString())
            ->whereHas('order', fn (Builder $query) => $query->where('payment_mode', PaymentMode::IMPORTED));
    }

    /**
     * The row's date (Y-m-d) when the row says the lesson happened and the
     * date is before today; null otherwise.
     *
     * @param  array<string, string|int>  $row
     */
    protected function completedRowDate(array $row): ?string
    {
        $date = ImportValues::date((string) ($row['date'] ?? ''));

        if ($date === null || ! $date->lessThan(now()->startOfDay())) {
            return null;
        }

        return ImportDiaryItemAction::resolveStatus($row) === 'completed' ? $date->toDateString() : null;
    }
}
