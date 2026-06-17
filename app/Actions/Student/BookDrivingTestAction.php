<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\CalendarItem;
use App\Models\Student;
use App\Services\InstructorService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookDrivingTestAction
{
    public function __construct(
        protected InstructorService $instructorService,
        protected CancelDrivingTestAction $cancelDrivingTest,
    ) {}

    /**
     * Book a practical driving test for a pupil.
     *
     * Creates a practical-test calendar item on the pupil's instructor's diary
     * (1hr prep + 1hr test + 30min buffer) and ticks the `book_practical_test`
     * checklist row with the test date, linking the two together.
     *
     * If a test was previously booked for this pupil, it is cancelled first so
     * the pupil only ever has one active test on the diary.
     */
    public function __invoke(Student $student, string $date, string $startTime): CalendarItem
    {
        $instructor = $student->instructor;

        if (! $instructor) {
            throw new RuntimeException('Pupil has no assigned instructor — cannot book a driving test.');
        }

        return DB::transaction(function () use ($student, $instructor, $date, $startTime) {
            // Cancel any existing test so the pupil/diary stay 1:1.
            ($this->cancelDrivingTest)($student);

            // Test is a 1-hour appointment; the action expands it to prep + test + buffer.
            $endTime = \Carbon\Carbon::parse($startTime)->addMinutes(60)->format('H:i');

            $calendarItem = $this->instructorService->addCalendarItem(
                instructor: $instructor,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                isAvailable: false,
                notes: trim($student->first_name.' '.$student->surname),
                unavailabilityReason: 'Practical Test',
                travelTimeMinutes: null,
                isPracticalTest: true,
                student: $student,
            );

            $checklistItem = $student->checklistItems()
                ->where('key', 'book_practical_test')
                ->first();

            if ($checklistItem) {
                $checklistItem->update([
                    'is_checked' => true,
                    'date' => $date,
                    'calendar_item_id' => $calendarItem->id,
                ]);
            }

            return $calendarItem;
        });
    }
}
