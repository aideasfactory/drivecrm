<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Lesson;
use App\Models\ReflectiveLog;

class SaveReflectiveLogAction
{
    /**
     * Upsert the reflective log for a lesson.
     *
     * @param  array{what_i_learned: ?string, what_went_well: ?string, what_to_improve: ?string, additional_notes?: ?string}  $fields
     */
    public function __invoke(Lesson $lesson, array $fields): ReflectiveLog
    {
        return ReflectiveLog::query()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'what_i_learned' => $fields['what_i_learned'] ?? null,
                'what_went_well' => $fields['what_went_well'] ?? null,
                'what_to_improve' => $fields['what_to_improve'] ?? null,
                'additional_notes' => $fields['additional_notes'] ?? null,
            ]
        );
    }
}
