<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Resource\GetPublishedResourcesAction;
use App\Actions\Resource\GetResourceFolderTreeAction;
use App\Actions\Resource\GetResourceSummaryAction;
use App\Actions\Resource\GetStudentSuggestedResourceIdsAction;
use App\Actions\Resource\GetUserWatchedResourceIdsAction;
use App\Actions\Resource\MarkResourceWatchedAction;
use App\Actions\Student\Lesson\AssignResourcesToLessonAction;
use App\Enums\ResourceAudience;
use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResourceApiService extends BaseService
{
    public function __construct(
        protected GetPublishedResourcesAction $getPublishedResources,
        protected AssignResourcesToLessonAction $assignResources,
        protected GetResourceFolderTreeAction $getResourceFolderTree,
        protected GetResourceSummaryAction $getResourceSummary,
        protected GetStudentSuggestedResourceIdsAction $getSuggestedResourceIds,
        protected GetUserWatchedResourceIdsAction $getWatchedResourceIds,
        protected MarkResourceWatchedAction $markResourceWatched
    ) {}

    /**
     * Get all published resources, optionally filtered by audience.
     */
    public function getPublishedResources(?ResourceAudience $audience = null): Collection
    {
        if ($audience !== null) {
            return ($this->getPublishedResources)($audience);
        }

        return $this->remember('resources:published', fn () => ($this->getPublishedResources)());
    }

    /**
     * Get the full resource folder tree with published resources.
     *
     * @return EloquentCollection<int, \App\Models\ResourceFolder>
     */
    public function getResourceFolderTree(): EloquentCollection
    {
        return $this->remember('resources:folder_tree', fn () => ($this->getResourceFolderTree)());
    }

    /**
     * Get the IDs of resources suggested to a student (via lesson_resource).
     *
     * @return Collection<int, int>
     */
    public function getSuggestedResourceIds(Student $student): Collection
    {
        $key = $this->cacheKey('student', $student->id, 'suggested_resource_ids');

        return $this->remember($key, fn () => ($this->getSuggestedResourceIds)($student));
    }

    /**
     * Get the IDs of resources watched by a user.
     *
     * @return Collection<int, int>
     */
    public function getWatchedResourceIds(User $user): Collection
    {
        $key = $this->cacheKey('user', $user->id, 'watched_resource_ids');

        return $this->remember($key, fn () => ($this->getWatchedResourceIds)($user));
    }

    /**
     * Get the student's suggested resources with metadata (flat list for "My Resources" tab).
     *
     * @return Collection<int, object>
     */
    public function getMyResources(Student $student): Collection
    {
        $key = $this->cacheKey('student', $student->id, 'my_resources');

        return $this->remember($key, function () use ($student) {
            return DB::table('lesson_resource')
                ->join('lessons', 'lessons.id', '=', 'lesson_resource.lesson_id')
                ->join('orders', 'orders.id', '=', 'lessons.order_id')
                ->join('resources', 'resources.id', '=', 'lesson_resource.resource_id')
                ->leftJoin('resource_folders', 'resource_folders.id', '=', 'resources.resource_folder_id')
                ->where('orders.student_id', $student->id)
                ->where('resources.status', 'published')
                ->where('resources.audience', 'student')
                ->select([
                    'resources.id as resource_id',
                    'resources.title as resource_title',
                    'resources.resource_type',
                    'resources.thumbnail_url',
                    'resource_folders.name as folder_name',
                    'lesson_resource.created_at as suggested_at',
                ])
                ->distinct()
                ->orderByDesc('lesson_resource.created_at')
                ->get();
        });
    }

    /**
     * Get the aggregated resource summary for the student dashboard.
     *
     * Not cached — contains random study tip and volatile watch data.
     *
     * @return array{recent_activity: \Illuminate\Support\Collection, stats: array, study_progress: \Illuminate\Support\Collection, recommended: \Illuminate\Support\Collection, study_tip: string}
     */
    public function getResourceSummary(Student $student, User $user): array
    {
        return ($this->getResourceSummary)($student, $user);
    }

    /**
     * Get a single published resource by ID, optionally scoped to an audience.
     */
    public function getPublishedResource(int $resourceId, ?ResourceAudience $audience = null): Resource
    {
        return Resource::query()
            ->published()
            ->when($audience, fn ($q, $a) => $q->where('audience', $a))
            ->findOrFail($resourceId);
    }

    /**
     * Get a signed URL for a file resource, or the video URL for video links.
     */
    public function getResourceUrl(Resource $resource): string
    {
        if ($resource->isVideoLink()) {
            return $resource->video_url;
        }

        return Storage::disk('s3')->temporaryUrl(
            $resource->file_path,
            now()->addMinutes(30)
        );
    }

    /**
     * Mark a resource as watched by a user. Idempotent.
     */
    public function markAsWatched(User $user, Resource $resource): void
    {
        ($this->markResourceWatched)($user, $resource);

        $this->invalidate($this->cacheKey('user', $user->id, 'watched_resource_ids'));
    }

    /**
     * Get 5 random published resources (fallback when student has no suggestions).
     *
     * Returns the same column shape as getMyResources() so MyResourceResource works.
     */
    public function getRandomPublishedResources(int $limit = 5): Collection
    {
        return DB::table('resources')
            ->leftJoin('resource_folders', 'resource_folders.id', '=', 'resources.resource_folder_id')
            ->where('resources.status', 'published')
            ->where('resources.audience', 'student')
            ->select([
                'resources.id as resource_id',
                'resources.title as resource_title',
                'resources.resource_type',
                'resources.thumbnail_url',
                'resource_folders.name as folder_name',
                DB::raw('NULL as suggested_at'),
            ])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Assign resources to a lesson and email the student.
     *
     * @param  array<int>  $resourceIds
     */
    public function assignResourcesToLesson(Lesson $lesson, array $resourceIds, Student $student): void
    {
        ($this->assignResources)($lesson, $resourceIds, $student);

        $this->invalidate([
            'resources:published',
            'resources:published:'.ResourceAudience::STUDENT->value,
            'resources:published:'.ResourceAudience::INSTRUCTOR->value,
            'resources:folder_tree',
            'resources:folder_tree:'.ResourceAudience::STUDENT->value,
            'resources:folder_tree:'.ResourceAudience::INSTRUCTOR->value,
            $this->cacheKey('student', $student->id, 'suggested_resource_ids'),
            $this->cacheKey('student', $student->id, 'my_resources'),
        ]);
    }
}
