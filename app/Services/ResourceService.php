<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Resource\BulkImportResourcesAction;
use App\Actions\Resource\CreateFolderAction;
use App\Actions\Resource\DeleteFolderAction;
use App\Actions\Resource\DeleteResourceAction;
use App\Actions\Resource\GetFolderBreadcrumbsAction;
use App\Actions\Resource\GetFolderContentsAction;
use App\Actions\Resource\StoreVideoLinkResourceAction;
use App\Actions\Resource\UpdateFolderAction;
use App\Actions\Resource\UpdateResourceAction;
use App\Actions\Resource\UploadResourceAction;
use App\Models\Resource;
use App\Models\ResourceFolder;
use Illuminate\Http\UploadedFile;

class ResourceService
{
    public function __construct(
        protected BulkImportResourcesAction $bulkImportResources,
        protected GetFolderContentsAction $getFolderContents,
        protected GetFolderBreadcrumbsAction $getFolderBreadcrumbs,
        protected CreateFolderAction $createFolder,
        protected UpdateFolderAction $updateFolder,
        protected DeleteFolderAction $deleteFolder,
        protected UploadResourceAction $uploadResource,
        protected StoreVideoLinkResourceAction $storeVideoLinkResource,
        protected UpdateResourceAction $updateResource,
        protected DeleteResourceAction $deleteResource
    ) {}

    /**
     * Get folder contents (subfolders + resources).
     *
     * @return array{folders: \Illuminate\Database\Eloquent\Collection, resources: \Illuminate\Database\Eloquent\Collection}
     */
    public function getFolderContents(?ResourceFolder $folder = null): array
    {
        return ($this->getFolderContents)($folder);
    }

    /**
     * Get breadcrumb trail for a folder.
     *
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public function getFolderBreadcrumbs(?ResourceFolder $folder = null): array
    {
        return ($this->getFolderBreadcrumbs)($folder);
    }

    /**
     * Create a new folder.
     */
    public function createFolder(string $name, ?int $parentId = null): ResourceFolder
    {
        return ($this->createFolder)($name, $parentId);
    }

    /**
     * Rename a folder.
     */
    public function updateFolder(ResourceFolder $folder, string $name): ResourceFolder
    {
        return ($this->updateFolder)($folder, $name);
    }

    /**
     * Delete a folder and all contents.
     */
    public function deleteFolder(ResourceFolder $folder): void
    {
        ($this->deleteFolder)($folder);
    }

    /**
     * Upload a resource file.
     */
    public function uploadResource(
        ResourceFolder $folder,
        UploadedFile $file,
        string $title,
        ?string $description = null,
        ?array $tags = null
    ): Resource {
        return ($this->uploadResource)($folder, $file, $title, $description, $tags);
    }

    /**
     * Store a video link resource (Vimeo/YouTube).
     */
    public function storeVideoLinkResource(
        ResourceFolder $folder,
        string $videoUrl,
        string $title,
        ?string $description = null,
        ?array $tags = null,
        ?string $thumbnailUrl = null
    ): Resource {
        return ($this->storeVideoLinkResource)($folder, $videoUrl, $title, $description, $tags, $thumbnailUrl);
    }

    /**
     * Update resource metadata.
     */
    public function updateResource(Resource $resource, string $title, ?string $description = null, ?array $tags = null, ?string $thumbnailUrl = null): Resource
    {
        return ($this->updateResource)($resource, $title, $description, $tags, $thumbnailUrl);
    }

    /**
     * Delete a resource and its S3 file.
     */
    public function deleteResource(Resource $resource): void
    {
        ($this->deleteResource)($resource);
    }

    /**
     * Bulk import video link resources from parsed CSV rows.
     *
     * @param  array<int, array<string, string>>  $rows  Parsed CSV rows
     * @param  ResourceFolder  $folder  Target folder
     * @return array{imported: int, skipped: int, errors: array<int, array{row: int, field: string|null, message: string}>}
     */
    public function bulkImportResources(array $rows, ResourceFolder $folder): array
    {
        return ($this->bulkImportResources)($rows, $folder);
    }

    /**
     * Parse a CSV file into an array of associative rows.
     *
     * @return array<int, array<string, string>>
     */
    public function parseCsvFile(\Illuminate\Http\UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        // Normalize headers
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (count(array_filter($row, fn ($v) => trim($v) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($row, count($headers), ''));
        }

        fclose($handle);

        return $rows;
    }
}
