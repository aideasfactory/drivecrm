<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Models\Lesson;

class SaveLessonSummaryAction
{
    /**
     * Save the instructor's lesson summary to the lesson record. It replaces
     * any summary the importer brought over, so the pupil can see it.
     */
    public function __invoke(Lesson $lesson, string $summary): Lesson
    {
        $lesson->summary = $summary;
        $lesson->summary_from_import = false;
        $lesson->save();

        return $lesson;
    }
}
