<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Resource\CreateFolderAction;
use App\Actions\Resource\DeleteFolderAction;
use App\Actions\Resource\DeleteResourceAction;
use App\Actions\Resource\GetFolderBreadcrumbsAction;
use App\Actions\Resource\GetFolderContentsAction;
use App\Actions\Resource\UpdateFolderAction;
use App\Actions\Resource\UpdateResourceAction;
use App\Actions\Resource\UploadResourceAction;
use App\Models\Resource;
use App\Models\ResourceFolder;
use Illuminate\Http\UploadedFile;

class ResourceService
{
    public function __construct(
        protected GetFolderContentsAction $getFolderContents,
        protected GetFolderBreadcrumbsAction $getFolderBreadcrumbs,
        protected CreateFolderAction $createFolder,
        protected UpdateFolderAction $updateFolder,
        protected DeleteFolderAction $deleteFolder,
        protected UploadResourceAction $uploadResource,
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
     * Update resource metadata.
     */
    public function updateResource(Resource $resource, string $title, ?string $description = null, ?array $tags = null): Resource
    {
        return ($this->updateResource)($resource, $title, $description, $tags);
    }

    /**
     * Delete a resource and its S3 file.
     */
    public function deleteResource(Resource $resource): void
    {
        ($this->deleteResource)($resource);
    }
}
