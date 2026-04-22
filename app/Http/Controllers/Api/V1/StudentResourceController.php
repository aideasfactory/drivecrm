<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ResourceAudience;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\MyResourceResource;
use App\Http\Resources\V1\ResourceFolderTreeResource;
use App\Http\Resources\V1\StudentResourceDetailResource;
use App\Services\ResourceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentResourceController extends Controller
{
    public function __construct(
        protected ResourceApiService $resourceService
    ) {}

    /**
     * Get the aggregated resource summary for the Resources tab dashboard.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->student;

        $summary = $this->resourceService->getResourceSummary($student, $user);

        return response()->json(['data' => $summary]);
    }

    /**
     * Get the full resource library for the student.
     *
     * Returns the folder tree with all published resources (each annotated with
     * is_suggested and is_watched flags) plus a flat "my_resources" array of
     * resources suggested to this student via lesson sign-offs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->student;

        $folders = $this->resourceService->getResourceFolderTree();
        $suggestedIds = $this->resourceService->getSuggestedResourceIds($student);
        $watchedIds = $this->resourceService->getWatchedResourceIds($user);
        $myResources = $this->resourceService->getMyResources($student);

        // Set static context for nested resource serialization
        ResourceFolderTreeResource::$suggestedIds = $suggestedIds;
        ResourceFolderTreeResource::$watchedIds = $watchedIds;

        return response()->json([
            'data' => [
                'folders' => ResourceFolderTreeResource::collection($folders),
                'my_resources' => MyResourceResource::collection($myResources),
            ],
        ]);
    }

    /**
     * Get a single resource with its video URL or signed file URL.
     */
    public function show(Request $request, int $resource): JsonResponse
    {
        $user = $request->user();
        $resourceModel = $this->resourceService->getPublishedResource($resource, ResourceAudience::STUDENT);

        $watchedIds = $this->resourceService->getWatchedResourceIds($user);

        $detail = new StudentResourceDetailResource($resourceModel);
        $detail->isWatched = $watchedIds->contains($resourceModel->id);

        if (! $resourceModel->isVideoLink()) {
            $detail->fileUrl = $this->resourceService->getResourceUrl($resourceModel);
        }

        return $detail->response();
    }

    /**
     * Mark a resource as watched by the authenticated user. Idempotent.
     */
    public function markWatched(Request $request, int $resource): JsonResponse
    {
        $user = $request->user();
        $resourceModel = $this->resourceService->getPublishedResource($resource, ResourceAudience::STUDENT);

        $this->resourceService->markAsWatched($user, $resourceModel);

        return response()->json(['message' => 'Resource marked as watched.']);
    }
}
