<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ResourceAudience;
use App\Http\Requests\ImportResourcesCsvRequest;
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
     * Store a new resource (file upload or video link).
     */
    public function storeResource(StoreResourceRequest $request): JsonResponse
    {
        $folder = ResourceFolder::findOrFail($request->validated('resource_folder_id'));
        $audience = ResourceAudience::from($request->validated('audience'));

        if ($request->validated('resource_type') === 'video_link') {
            $resource = $this->resourceService->storeVideoLinkResource(
                $folder,
                $request->validated('video_url'),
                $request->validated('title'),
                $audience,
                $request->validated('description'),
                $request->validated('tags'),
                $request->validated('thumbnail_url')
            );
        } else {
            $resource = $this->resourceService->uploadResource(
                $folder,
                $request->file('file'),
                $request->validated('title'),
                $audience,
                $request->validated('description'),
                $request->validated('tags')
            );
        }

        return response()->json([
            'message' => 'Resource created successfully.',
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
            $request->validated('tags'),
            $request->validated('thumbnail_url'),
            ResourceAudience::from($request->validated('audience'))
        );

        return response()->json([
            'message' => 'Resource updated successfully.',
            'resource' => $resource,
        ]);
    }

    /**
     * Get a temporary signed URL for a resource file, or the video URL for video links.
     */
    public function getFileUrl(Resource $resource): JsonResponse
    {
        if ($resource->isVideoLink()) {
            return response()->json(['url' => $resource->video_url]);
        }

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

        if ($resource->isVideoLink()) {
            return redirect()->away($resource->video_url);
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $resource->file_path,
            now()->addMinutes(30)
        );

        return redirect()->away($url);
    }

    /**
     * Download the resource CSV import template.
     */
    public function downloadCsvTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = ['title', 'video_url', 'description', 'tags', 'folder', 'thumbnail_url', 'audience'];
        $exampleRow = ['Introduction to Driving Theory', 'https://www.youtube.com/watch?v=example', 'A video covering basic driving theory', 'theory,beginner,driving', 'Theory/Basics', 'https://img.youtube.com/vi/example/0.jpg', 'student'];

        return response()->streamDownload(function () use ($headers, $exampleRow) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $exampleRow);
            fclose($handle);
        }, 'resources-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Import video link resources from an uploaded CSV file.
     */
    public function importCsv(ImportResourcesCsvRequest $request): JsonResponse
    {
        $folder = ResourceFolder::findOrFail($request->validated('resource_folder_id'));

        $rows = $this->resourceService->parseCsvFile($request->file('file'));

        if (empty($rows)) {
            return response()->json([
                'message' => 'The CSV file is empty or could not be parsed.',
                'imported' => 0,
                'skipped' => 0,
                'errors' => [],
            ], 422);
        }

        $result = $this->resourceService->bulkImportResources($rows, $folder);

        return response()->json([
            'message' => "{$result['imported']} resource(s) imported successfully.",
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
        ]);
    }
}
