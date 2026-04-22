<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\Resource;
use App\Models\ResourceFolder;
use Illuminate\Http\UploadedFile;

class UploadResourceAction
{
    /**
     * Upload a file to S3 and create a resource record.
     */
    public function __invoke(
        ResourceFolder $folder,
        UploadedFile $file,
        string $title,
        ResourceAudience $audience,
        ?string $description = null,
        ?array $tags = null
    ): Resource {
        $fileName = $file->getClientOriginalName();
        $path = $file->store("resources/{$folder->id}", 's3');

        return Resource::create([
            'resource_folder_id' => $folder->id,
            'resource_type' => 'file',
            'audience' => $audience,
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'file_path' => $path,
            'file_name' => $fileName,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }
}
