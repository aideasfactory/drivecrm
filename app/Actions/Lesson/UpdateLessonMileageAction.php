<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Models\Lesson;

class UpdateLessonMileageAction
{
    public function __invoke(Lesson $lesson, ?int $mileage): Lesson
    {
        $lesson->update(['mileage' => $mileage]);

        return $lesson;
    }
}
