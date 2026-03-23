<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Resource\GetPublishedResourcesAction;
use App\Actions\Student\Lesson\AssignResourcesToLessonAction;
use App\Models\Lesson;
use App\Models\Student;
use Illuminate\Support\Collection;

class ResourceApiService extends BaseService
{
    public function __construct(
        protected GetPublishedResourcesAction $getPublishedResources,
        protected AssignResourcesToLessonAction $assignResources
    ) {}

    /**
     * Get all published resources.
     */
    public function getPublishedResources(): Collection
    {
        return $this->remember('resources:published', fn () => ($this->getPublishedResources)());
    }

    /**
     * Assign resources to a lesson and email the student.
     *
     * @param  array<int>  $resourceIds
     */
    public function assignResourcesToLesson(Lesson $lesson, array $resourceIds, Student $student): void
    {
        ($this->assignResources)($lesson, $resourceIds, $student);

        $this->invalidate('resources:published');
    }
}
