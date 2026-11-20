<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Models\ImportMapping;
use App\Models\InstructorFinance;
use App\Models\Lesson;
use App\Models\LessonDeletionRequest;

class PermanentlyDeleteLessonAction
{
    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * Hard-delete a lesson and the rows that only exist for it. Call inside a
     * transaction, and only for a lesson GetLessonDeletionBlockerAction clears
     * (no payout and no money taken), so the only payment row removed is an
     * unpaid one with no open invoice.
     *
     * Removed: the unpaid lesson payment, sent reminders, attached resources,
     * the reflective log, the `lesson` import mapping, and the past diary slot
     * when no other lesson uses it (not reopened as free time). The `diary`
     * import mapping is kept so a re-import still skips the slot instead of
     * bringing the lesson back. Deletion requests and instructor finance rows
     * are detached, not deleted. Activity logs and notes keep their history.
     *
     * @return array{calendar_date: string|null}
     */
    public function __invoke(Lesson $lesson): array
    {
        $calendarItem = $lesson->calendarItem;
        $calendarDate = $calendarItem?->calendar?->date?->format('Y-m-d') ?? $lesson->date?->format('Y-m-d');

        LessonDeletionRequest::query()->where('lesson_id', $lesson->id)->update(['lesson_id' => null, 'pending_lesson_id' => null]);
        InstructorFinance::query()->where('lesson_id', $lesson->id)->update(['lesson_id' => null]);

        ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_LESSON)
            ->where('model_id', $lesson->id)
            ->delete();

        $lesson->lessonPayment()->delete();
        $lesson->reminders()->delete();
        $lesson->resources()->detach();
        $lesson->reflectiveLog()->delete();

        $lesson->delete();

        if ($calendarItem && ! $calendarItem->lessons()->exists()) {
            ($this->deleteCalendarItem)($calendarItem);
        }

        return ['calendar_date' => $calendarDate];
    }
}
