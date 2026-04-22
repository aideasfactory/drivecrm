<?php

declare(strict_types=1);

namespace App\Actions\Resource;

use App\Enums\ResourceAudience;
use App\Models\Resource;
use App\Models\ResourceFolder;

class StoreVideoLinkResourceAction
{
    /**
     * Create a resource record for a video link (Vimeo/YouTube).
     */
    public function __invoke(
        ResourceFolder $folder,
        string $videoUrl,
        string $title,
        ResourceAudience $audience,
        ?string $description = null,
        ?array $tags = null,
        ?string $thumbnailUrl = null
    ): Resource {
        return Resource::create([
            'resource_folder_id' => $folder->id,
            'resource_type' => 'video_link',
            'audience' => $audience,
            'video_url' => $videoUrl,
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'thumbnail_url' => $thumbnailUrl,
        ]);
    }
}
