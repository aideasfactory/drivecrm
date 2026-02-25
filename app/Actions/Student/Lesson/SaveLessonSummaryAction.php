<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Lesson;

class SaveLessonSummaryAction
{
    /**
     * Save the instructor's lesson summary to the lesson record.
     */
    public function __invoke(Lesson $lesson, string $summary): Lesson
    {
        $lesson->summary = $summary;
        $lesson->save();

        return $lesson;
    }
}
