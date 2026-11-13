<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\CalendarItemType;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\ImportMapping;
use App\Models\ImportRun;
use App\Models\Instructor;
use App\Models\Student;

class CreateImportedPracticalTestSlotAction
{
    /**
     * Put a legacy driving test in the instructor's diary as a practical test
     * for the pupil — the same shape as `import:practical-tests` produced and
     * as a test booked in Drive+: a calendar item `practical_test` with the
     * pupil's `student_id`, notes prefixed with the pupil's name. No lesson,
     * no order, no payment. Mapped as `diary` so a re-import skips it.
     *
     * @param  string  $date  Y-m-d
     * @param  string  $startTime  H:i
     * @param  string  $endTime  H:i
     */
    public function __invoke(
        Instructor $instructor,
        Student $student,
        string $diaryRef,
        string $date,
        string $startTime,
        string $endTime,
        ?string $notes,
        ImportRun $run,
    ): CalendarItem {
        $calendar = Calendar::query()->firstOrCreate([
            'instructor_id' => $instructor->id,
            'date' => $date,
        ]);

        $name = trim($student->first_name.' '.$student->surname);
        $notes = trim((string) $notes);

        if ($name !== '' && ! str_starts_with($notes, $name)) {
            $notes = trim($name.($notes !== '' ? ' - '.$notes : ''));
        }

        $item = CalendarItem::create([
            'calendar_id' => $calendar->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_available' => false,
            'status' => null,
            'item_type' => CalendarItemType::PracticalTest,
            'student_id' => $student->id,
            'notes' => $notes !== '' ? $notes : null,
            'unavailability_reason' => 'Practical Test',
        ]);

        ImportMapping::record($run, ImportMapping::ENTITY_DIARY, $diaryRef, $item->id);

        return $item;
    }
}
