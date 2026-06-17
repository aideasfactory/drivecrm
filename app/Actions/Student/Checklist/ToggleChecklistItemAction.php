<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Actions\Student\BookDrivingTestAction;
use App\Actions\Student\CancelDrivingTestAction;
use App\Models\StudentChecklistItem;

class ToggleChecklistItemAction
{
    public function __construct(
        protected BookDrivingTestAction $bookDrivingTest,
        protected CancelDrivingTestAction $cancelDrivingTest,
    ) {}

    /**
     * Toggle a checklist item's checked state with an optional date and notes.
     *
     * When checking: requires a date, optionally accepts notes.
     * When unchecking: clears the date and notes.
     *
     * Special case: the `book_practical_test` item is mirrored to the
     * instructor's diary as a practical-test calendar slot. Ticking creates the
     * slot, unticking removes it. The default test time is 11:00 unless the
     * caller passes one through (the dedicated Book Driving Test dialog does).
     *
     * @param  array{is_checked: bool, date?: string|null, notes?: string|null, start_time?: string|null}  $data
     */
    public function __invoke(StudentChecklistItem $item, array $data): StudentChecklistItem
    {
        $isChecked = (bool) $data['is_checked'];
        $isPracticalTestRow = $item->key === 'book_practical_test';

        if ($isChecked) {
            if ($isPracticalTestRow && ! empty($data['date']) && $item->student) {
                ($this->bookDrivingTest)(
                    $item->student,
                    (string) $data['date'],
                    (string) ($data['start_time'] ?? '11:00'),
                );

                return $item->fresh();
            }

            $item->update([
                'is_checked' => true,
                'date' => $data['date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            if ($isPracticalTestRow && $item->student) {
                ($this->cancelDrivingTest)($item->student);

                return $item->fresh();
            }

            $item->update([
                'is_checked' => false,
                'date' => null,
                'notes' => null,
            ]);
        }

        return $item->fresh();
    }
}
