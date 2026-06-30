<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ResourceAudience;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InstructorResourceFolderTreeResource;
use App\Http\Resources\V1\ResourceDetailResource;
use App\Http\Resources\V1\ResourceResource;
use App\Services\ResourceApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResourceController extends Controller
{
    public function __construct(
        protected ResourceApiService $resourceService
    ) {}

    /**
     * Return published resources, optionally filtered by audience.
     *
     * Query params:
     * - audience=student    → only student resources
     * - audience=instructor → only instructor resources
     * - (omitted)           → all resources
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'audience' => ['nullable', 'string', 'in:student,instructor'],
        ]);

        $audience = isset($validated['audience'])
            ? ResourceAudience::from($validated['audience'])
            : null;

        $resources = $this->resourceService->getPublishedResources($audience);

        return ResourceResource::collection($resources);
    }

    /**
     * Return the folder tree with published resources nested inside, for the instructor app.
     *
     * Same folders → children → resources structure as the student tree, but not scoped to
     * `audience = 'student'`: both audiences are returned by default (each resource keeps its
     * `audience`), with an optional audience to narrow server-side. Empty folders are pruned.
     *
     * Query params:
     * - audience=student    → only student resources
     * - audience=instructor → only instructor resources
     * - (omitted)           → both
     */
    public function tree(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['nullable', 'string', 'in:student,instructor'],
        ]);

        $audience = isset($validated['audience'])
            ? ResourceAudience::from($validated['audience'])
            : null;

        $folders = $this->resourceService->getInstructorResourceFolderTree($audience);

        return response()->json([
            'data' => [
                'folders' => InstructorResourceFolderTreeResource::collection($folders),
            ],
        ]);
    }

    /**
     * Return a single published resource with a freshly signed file URL for file resources.
     */
    public function show(int $resource): JsonResponse
    {
        $resourceModel = $this->resourceService->getPublishedResource($resource);

        $detail = new ResourceDetailResource($resourceModel);

        if (! $resourceModel->isVideoLink()) {
            $detail->fileUrl = $this->resourceService->getResourceUrl($resourceModel);
        }

        return $detail->response();
    }
}
