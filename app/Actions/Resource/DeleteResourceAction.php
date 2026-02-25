<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Models\Resource;
use Illuminate\Support\Facades\Storage;

class DeleteResourceAction
{
    /**
     * Delete a resource and its file from S3.
     */
    public function __invoke(Resource $resource): void
    {
        // Delete the file from S3
        Storage::disk('s3')->delete($resource->file_path);

        if ($resource->thumbnail_path) {
            Storage::disk('s3')->delete($resource->thumbnail_path);
        }

        $resource->delete();
    }
}
