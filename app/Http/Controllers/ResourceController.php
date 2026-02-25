<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\StoreResourceRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Http\Requests\UpdateResourceRequest;
use App\Models\Resource;
use App\Models\ResourceFolder;
use App\Services\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function __construct(
        protected ResourceService $resourceService
    ) {}

    /**
     * Display the resources index page.
     */
    public function index(): Response
    {
        return Inertia::render('Resources/Index');
    }

    /**
     * Get folder contents (subfolders + resources) as JSON.
     */
    public function getFolderContents(?ResourceFolder $folder = null): JsonResponse
    {
        $contents = $this->resourceService->getFolderContents($folder);
        $breadcrumbs = $this->resourceService->getFolderBreadcrumbs($folder);

        return response()->json([
            'folders' => $contents['folders'],
            'resources' => $contents['resources'],
            'breadcrumbs' => $breadcrumbs,
            'current_folder' => $folder ? [
                'id' => $folder->id,
                'name' => $folder->name,
                'slug' => $folder->slug,
            ] : null,
        ]);
    }

    /**
     * Create a new folder.
     */
    public function storeFolder(StoreFolderRequest $request): JsonResponse
    {
        $folder = $this->resourceService->createFolder(
            $request->validated('name'),
            $request->validated('parent_id')
        );

        return response()->json([
            'message' => 'Folder created successfully.',
            'folder' => $folder,
        ], 201);
    }

    /**
     * Rename an existing folder.
     */
    public function updateFolder(UpdateFolderRequest $request, ResourceFolder $folder): JsonResponse
    {
        $folder = $this->resourceService->updateFolder(
            $folder,
            $request->validated('name')
        );

        return response()->json([
            'message' => 'Folder renamed successfully.',
            'folder' => $folder,
        ]);
    }

    /**
     * Delete a folder and all its contents.
     */
    public function destroyFolder(ResourceFolder $folder): JsonResponse
    {
        $this->resourceService->deleteFolder($folder);

        return response()->json([
            'message' => 'Folder deleted successfully.',
        ]);
    }

    /**
     * Upload a new resource file.
     */
    public function storeResource(StoreResourceRequest $request): JsonResponse
    {
        $folder = ResourceFolder::findOrFail($request->validated('resource_folder_id'));

        $resource = $this->resourceService->uploadResource(
            $folder,
            $request->file('file'),
            $request->validated('title'),
            $request->validated('description'),
            $request->validated('tags')
        );

        return response()->json([
            'message' => 'Resource uploaded successfully.',
            'resource' => $resource,
        ], 201);
    }

    /**
     * Update resource metadata.
     */
    public function updateResource(UpdateResourceRequest $request, Resource $resource): JsonResponse
    {
        $resource = $this->resourceService->updateResource(
            $resource,
            $request->validated('title'),
            $request->validated('description'),
            $request->validated('tags')
        );

        return response()->json([
            'message' => 'Resource updated successfully.',
            'resource' => $resource,
        ]);
    }

    /**
     * Get a temporary signed URL for a resource file.
     */
    public function getFileUrl(Resource $resource): JsonResponse
    {
        $url = Storage::disk('s3')->temporaryUrl(
            $resource->file_path,
            now()->addMinutes(30)
        );

        return response()->json(['url' => $url]);
    }

    /**
     * Delete a resource and its S3 file.
     */
    public function destroyResource(Resource $resource): JsonResponse
    {
        $this->resourceService->deleteResource($resource);

        return response()->json([
            'message' => 'Resource deleted successfully.',
        ]);
    }

    /**
     * View a resource via signed URL (from email links).
     *
     * Generates a fresh S3 temporary URL and redirects the user.
     * Protected by Laravel's signed URL verification (no auth required).
     */
    public function emailView(Request $request, Resource $resource): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This link has expired or is invalid.');
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $resource->file_path,
            now()->addMinutes(30)
        );

        return redirect()->away($url);
    }
}
