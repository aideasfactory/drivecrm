<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\Lesson;

class ConvertImportedLessonToPracticalTestAction
{
    /** Diary notes starting with this mark a practical test in the legacy export. */
    public const NOTES_PATTERN = '/^\s*driving test\b/i';

    /**
     * Why an imported lesson can't be turned into a practical test, or null
     * when it can. Only lessons the importer created, on an imported order,
     * not cancelled or banked, with a calendar item, and with nothing that deleting the
     * lesson would lose (lesson payment, payout, reflective log).
     */
    public function blocker(?Lesson $lesson): ?string
    {
        if ($lesson === null || ! ImportMapping::wasImported(ImportMapping::ENTITY_LESSON, $lesson->id)) {
            return 'not_imported';
        }

        if ($lesson->order?->payment_mode !== PaymentMode::IMPORTED) {
            return 'not_imported';
        }

        if ($lesson->status === LessonStatus::CANCELLED) {
            return 'cancelled';
        }

        if ($lesson->banked_at !== null || $lesson->date === null) {
            return 'banked';
        }

        if (! $lesson->calendar_item_id || ! $lesson->calendarItem) {
            return 'no_calendar_item';
        }

        if ($lesson->lessonPayment()->exists() || $lesson->payout()->exists() || $lesson->reflectiveLog()->exists()) {
            return 'has_payment_or_log';
        }

        return null;
    }

    /**
     * Whether the pupil already has a practical test on that date (e.g. one
     * booked in Drive+, or a test converted earlier).
     */
    public function pupilHasTestOn(int $studentId, string $date, ?int $exceptCalendarItemId = null): bool
    {
        return CalendarItem::query()
            ->where('item_type', CalendarItemType::PracticalTest)
            ->where('student_id', $studentId)
            ->when($exceptCalendarItemId, fn ($query) => $query->whereKeyNot($exceptCalendarItemId))
            ->whereIn('calendar_id', Calendar::query()->whereDate('date', $date)->select('id'))
            ->exists();
    }

    /**
     * Turn an imported lesson's calendar item into a practical test for its
     * pupil and delete the lesson (a test is not a lesson). The slot keeps
     * its times; its notes are kept, prefixed with the pupil's name like a
     * test booked in Drive+. The `lesson` mapping is removed; the `diary`
     * mapping still points at the calendar item, so a re-import skips it.
     *
     * Call blocker() first. The caller recalculates the pupil's lesson
     * numbers, syncs the order and updates the checklist.
     */
    public function __invoke(Lesson $lesson): CalendarItem
    {
        $item = $lesson->calendarItem;
        $student = $lesson->order->student;
        $name = trim($student->first_name.' '.$student->surname);
        $notes = trim((string) $item->notes);

        if ($name !== '' && ! str_starts_with($notes, $name)) {
            $notes = trim($name.($notes !== '' ? ' - '.$notes : ''));
        }

        $item->update([
            'item_type' => CalendarItemType::PracticalTest,
            'student_id' => $student->id,
            'status' => null,
            'is_available' => false,
            'notes' => $notes !== '' ? $notes : null,
            'unavailability_reason' => 'Practical Test',
        ]);

        ImportMapping::query()
            ->where('entity', ImportMapping::ENTITY_LESSON)
            ->where('model_id', $lesson->id)
            ->delete();

        $lesson->delete();

        return $item;
    }
}
