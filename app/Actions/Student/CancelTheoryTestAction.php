<?php

declare(strict_types=1);

namespace App\Actions\Student;

use App\Models\CalendarItem;
use App\Models\Student;
use App\Services\InstructorService;
use Illuminate\Support\Facades\DB;

class CancelTheoryTestAction
{
    public function __construct(
        protected InstructorService $instructorService,
    ) {}

    /**
     * Cancel a pupil's booked theory test.
     *
     * Removes the linked theory-test calendar item from the instructor's diary
     * and unticks the `book_theory_test` checklist row. Safe to call even when
     * no test is booked — it just no-ops.
     */
    public function __invoke(Student $student): void
    {
        DB::transaction(function () use ($student) {
            // Look up the test in two places (defensive — both should point at the same row).
            $checklistItem = $student->checklistItems()
                ->where('key', 'book_theory_test')
                ->first();

            $candidates = collect([
                $checklistItem?->calendar_item_id,
            ])
                ->merge(
                    CalendarItem::query()
                        ->where('student_id', $student->id)
                        ->where('item_type', 'theory_test')
                        ->pluck('id')
                )
                ->filter()
                ->unique();

            foreach ($candidates as $calendarItemId) {
                $calendarItem = CalendarItem::find($calendarItemId);

                if ($calendarItem) {
                    $this->instructorService->removeCalendarItem($calendarItem);
                }
            }

            if ($checklistItem) {
                $checklistItem->update([
                    'is_checked' => false,
                    'date' => null,
                    'notes' => null,
                    'calendar_item_id' => null,
                ]);
            }
        });
    }
}
