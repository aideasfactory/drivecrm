<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\ResourceFolder;
use Illuminate\Support\Facades\Storage;

class DeleteFolderAction
{
    /**
     * Delete a folder and all its contents (sub-folders, resources, S3 files).
     */
    public function __invoke(ResourceFolder $folder): void
    {
        // Delete all resource files from S3 (recursively through sub-folders)
        $this->deleteResourceFiles($folder);

        // Cascade delete handled by DB foreign key, but we need to clean S3 first
        $folder->delete();
    }

    /**
     * Recursively delete all resource files from S3 for a folder and its children.
     */
    protected function deleteResourceFiles(ResourceFolder $folder): void
    {
        // Delete files for resources in this folder
        foreach ($folder->resources as $resource) {
            Storage::disk('s3')->delete($resource->file_path);

            if ($resource->thumbnail_path) {
                Storage::disk('s3')->delete($resource->thumbnail_path);
            }
        }

        // Recurse into child folders
        foreach ($folder->children as $child) {
            $this->deleteResourceFiles($child);
        }
    }
}
