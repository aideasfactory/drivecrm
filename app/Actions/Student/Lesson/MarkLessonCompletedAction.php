<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonStatus;
use App\Exceptions\LessonAlreadyCompletedException;
use App\Models\Lesson;

class MarkLessonCompletedAction
{
    /**
     * Mark a lesson as completed with a timestamp.
     *
     * @throws LessonAlreadyCompletedException
     */
    public function __invoke(Lesson $lesson): Lesson
    {
        if ($lesson->isCompleted()) {
            throw new LessonAlreadyCompletedException;
        }

        $lesson->status = LessonStatus::COMPLETED;
        $lesson->completed_at = now();
        $lesson->save();

        return $lesson;
    }
}
