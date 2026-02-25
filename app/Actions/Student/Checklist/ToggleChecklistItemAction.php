<?php

declare(strict_types=1);

namespace App\Actions\Student\Checklist;

use App\Models\StudentChecklistItem;

class ToggleChecklistItemAction
{
    /**
     * Toggle a checklist item's checked state with an optional date and notes.
     *
     * When checking: requires a date, optionally accepts notes.
     * When unchecking: clears the date and notes.
     *
     * @param  array{is_checked: bool, date?: string|null, notes?: string|null}  $data
     */
    public function __invoke(StudentChecklistItem $item, array $data): StudentChecklistItem
    {
        $isChecked = (bool) $data['is_checked'];

        if ($isChecked) {
            $item->update([
                'is_checked' => true,
                'date' => $data['date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } else {
            $item->update([
                'is_checked' => false,
                'date' => null,
                'notes' => null,
            ]);
        }

        return $item->fresh();
    }
}
