<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\CalendarItem;
use App\Models\Student;
use App\Services\InstructorService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookTheoryTestAction
{
    public function __construct(
        protected InstructorService $instructorService,
        protected CancelTheoryTestAction $cancelTheoryTest,
    ) {}

    /**
     * Book a theory test for a pupil.
     *
     * Creates a theory-test calendar item on the pupil's instructor's diary
     * (a single 1-hour slot — no prep/buffer like a practical) and ticks the
     * `book_theory_test` checklist row with the test date, linking the two.
     *
     * If a test was previously booked for this pupil, it is cancelled first so
     * the pupil only ever has one active theory test on the diary.
     */
    public function __invoke(Student $student, string $date, string $startTime): CalendarItem
    {
        $instructor = $student->instructor;

        if (! $instructor) {
            throw new RuntimeException('Pupil has no assigned instructor — cannot book a theory test.');
        }

        return DB::transaction(function () use ($student, $instructor, $date, $startTime) {
            // Cancel any existing theory test so the pupil/diary stay 1:1.
            ($this->cancelTheoryTest)($student);

            $endTime = \Carbon\Carbon::parse($startTime)->addMinutes(60)->format('H:i');

            $calendarItem = $this->instructorService->addCalendarItem(
                instructor: $instructor,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                isAvailable: false,
                notes: trim($student->first_name.' '.$student->surname),
                unavailabilityReason: 'Theory Test',
                travelTimeMinutes: null,
                isPracticalTest: false,
                student: $student,
                isTheoryTest: true,
            );

            $checklistItem = $student->checklistItems()
                ->where('key', 'book_theory_test')
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
