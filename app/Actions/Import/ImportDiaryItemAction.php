<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Support\ImportValues;

class ImportDiaryItemAction
{
    /**
     * Put one diary.csv row into the instructor's diary.
     *
     * - With a student + imported order: calendar item + lesson on that order.
     *   Past rows (or status `completed`) go straight in as completed — no
     *   sign-off pipeline, no payout, no emails. Future rows are booked and
     *   pending, ready to sign off like any other lesson.
     * - Without a student: an unavailable block carrying the row's notes.
     * - `cancelled` rows and rows already imported are skipped (returns null).
     *
     * @param  array<string, string|int>  $row
     */
    public function __invoke(Instructor $instructor, array $row, ?Order $order, int $lessonNumber, ImportRun $run): ?CalendarItem
    {
        $ref = (string) $row['diary_ref'];

        if (ImportMapping::modelIdFor(ImportMapping::ENTITY_DIARY, $ref) !== null) {
            return null;
        }

        $date = ImportValues::date((string) $row['date']);
        $startTime = ImportValues::time((string) $row['start_time']);
        $endTime = ImportValues::time((string) $row['end_time']);
        $status = self::resolveStatus($row);

        if ($status === 'cancelled') {
            return null;
        }

        $notes = $this->nullable($row, 'notes');

        $calendar = Calendar::query()->firstOrCreate([
            'instructor_id' => $instructor->id,
            'date' => $date->toDateString(),
        ]);

        if (! $order) {
            $item = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => false,
                'item_type' => CalendarItemType::Slot,
                'unavailability_reason' => $notes ?? 'Imported diary entry',
            ]);

            ImportMapping::record($run, ImportMapping::ENTITY_DIARY, $ref, $item->id);

            return $item;
        }

        $isCompleted = $status === 'completed';

        $item = CalendarItem::create([
            'calendar_id' => $calendar->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => false,
            'status' => $isCompleted ? CalendarItemStatus::COMPLETED : CalendarItemStatus::BOOKED,
            'item_type' => CalendarItemType::Slot,
            'notes' => $notes,
        ]);

        $price = $this->nullable($row, 'price');
        $mileage = $this->nullable($row, 'mileage');

        $lesson = Lesson::create([
            'order_id' => $order->id,
            'instructor_id' => $instructor->id,
            'amount_pence' => $price !== null ? (int) ImportValues::pence($price) : 0,
            'date' => $date->toDateString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'calendar_item_id' => $item->id,
            'status' => $isCompleted ? LessonStatus::COMPLETED : LessonStatus::PENDING,
            'completed_at' => $isCompleted ? $date->setTimeFromTimeString($endTime) : null,
            'summary' => $this->nullable($row, 'summary'),
            'mileage' => $mileage !== null ? (int) $mileage : null,
            'student_lesson_number' => $lessonNumber,
        ]);

        ImportMapping::record($run, ImportMapping::ENTITY_DIARY, $ref, $item->id);
        ImportMapping::record($run, ImportMapping::ENTITY_LESSON, $ref, $lesson->id);

        return $item;
    }

    /**
     * The row's status, defaulting to `completed` for past dates and `booked`
     * for today onwards.
     *
     * @param  array<string, string|int>  $row
     */
    public static function resolveStatus(array $row): string
    {
        $status = trim((string) ($row['status'] ?? ''));

        if ($status !== '') {
            return $status;
        }

        $date = ImportValues::date((string) $row['date']);

        return $date && $date->lessThan(now()->startOfDay()) ? 'completed' : 'booked';
    }

    /**
     * @param  array<string, string|int>  $row
     */
    protected function nullable(array $row, string $field): ?string
    {
        $value = trim((string) ($row[$field] ?? ''));

        return $value === '' ? null : $value;
    }
}
