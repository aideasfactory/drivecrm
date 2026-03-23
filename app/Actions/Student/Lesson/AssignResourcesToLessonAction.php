<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Mail\LessonResourcesAssigned;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Support\Facades\Mail;

class AssignResourcesToLessonAction
{
    /**
     * Attach resources to a lesson and email the student.
     *
     * @param  array<int>  $resourceIds
     */
    public function __invoke(Lesson $lesson, array $resourceIds, Student $student): void
    {
        $lesson->resources()->syncWithoutDetaching($resourceIds);

        $lesson->load('resources');

        $email = $student->email ?? $student->user?->email;

        if ($email && $lesson->resources->isNotEmpty()) {
            Mail::to($email)->queue(new LessonResourcesAssigned($lesson, $student));
        }
    }
}
